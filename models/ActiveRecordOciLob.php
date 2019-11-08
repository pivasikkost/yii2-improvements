<?php

namespace app\models\system;

use Yii;
use \yii\db\ActiveRecord;
use \yii\db\Query;

/**
 * ActiveRecordOciLob is the base class for classes representing relational data in terms of objects,
 * that should work correctly with LOB data in Oracle DB. Used Oci8Connection (in my case - neconix\src\Oci8Connection).
 * 
 * Just inherit your gii generated model class not from ActiveRecord, but from this class,
 * set $clob_attributes, $blob_attributes and $dbOciLobName to work.
 * 
 * @property array $clob_attributes an array of attribute names of the form "['name_1', 'name_2', ...]"
 * @property array $blob_attributes an array of attribute names of the form "['name_1', 'name_2', ...]"
 * @property array $primaryKeyOciLob an array of attribute names of the form "['name_1', 'name_2', ...]"
 * @property string $dbOciLobName Oci8Connection name
 * 
 * @author Konstantin Zosimenko <pivasikkost@gmail.com>
 * @since 2.0
 */
class ActiveRecordOciLob extends ActiveRecord
{
    // METHOD 4. Almost completed, it works!
    
    public static $clob_attributes = [];
    public static $blob_attributes = [];
    public static $dbOciLobName;
    public $primaryKeyOciLob; // Not static! for some reason, being static, when saving one descendant of this class from another descendant of this class, this variable is overwritten and takes the wrong value
    
    /** 
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->primaryKeyOciLob = static::primaryKey();
    }
    
    /**
     * @return \yii\db\Connection the database connection used by this AR class.
     */
    public static function getDbOciLob()
    {
        return static::$dbOciLobName 
                ? Yii::$app->get(static::$dbOciLobName)
                : static::getDb()
        ;
    }
    
    /**
     * Override ActiveRecord afterFind() method to fix LOB data receiveing
     * Maybe it's better to do this in populateRecord() method
     * 
     * @inheritdoc
     */
    public function afterFind ()
    {
        // Get lob fields as string, because standardly ActiveRecord gets them as resource
        $lob_attributes = array_merge(static::$clob_attributes, static::$blob_attributes);
        
        $where = [];
        foreach ($this->primaryKeyOciLob as $attribute) {
            $where[$attribute] = $this->$attribute;
        }

        foreach ($lob_attributes as $lob_attribute) {
            //$this->$lob_attribute = stream_get_contents($this->$lob_attribute); // Does not work, for some reason always returns 1 value if you try to get multiple records
            $this->$lob_attribute = (new Query())
                ->select($lob_attribute)
                ->from(static::tableName())
                ->where($where)
                ->createCommand(static::getDbOciLob())
                ->queryScalar();
            $this->setOldAttribute($lob_attribute, $this->$lob_attribute);
        }
        
        parent::afterFind ();
    }
    
    /**
     * Override ActiveRecord update() method to fix LOB data update
     * 
     * @inheritdoc
     */
    public function update($runValidation = true, $attributeNames = null)
    {   
        if ($runValidation && !$this->validate($attributeNames)) {
            Yii::info('Model not updated due to validation error.', __METHOD__);
            return false;
        }
        
        if (!$this->beforeSave(false)) {
            return false;
        }
        
        $db = static::getDbOciLob()->getDbh();
        $fields_blob = static::$blob_attributes;
        $fields_clob = static::$clob_attributes; // array with the fields to be updated
        $fields_dirty = $this->getDirtyAttributes($attributeNames); // changed fields with values
        $exist_fields_dirty_clob = array_intersect(array_keys($fields_dirty), $fields_clob);
        
        if (empty($fields_dirty)) {
            $this->afterSave(false, $fields_dirty);
            return 0;
        }
        
        $set = [];
        $into = [];
        foreach ($fields_dirty as $name => $value) {
            if (in_array($name, $fields_clob)) {
                $set[] = $name . " = EMPTY_CLOB()";
                $into[] = ":" . $name;
            /*} elseif (in_array($name, $fields_blob)) {
                $set[] = $name . " = EMPTY_BLOB()";
                $into[] = ":" . $name;*/
            } else {
                $set[] = $name . " = :" . $name;
            }
        }
        $set_stmt = implode(", ", $set); // array to string to fill 'set' clause in the sql
        $where = [];
        foreach ($this->primaryKeyOciLob as $attribute) {
            $where[] = $attribute . "=" .$this->$attribute;
        }
        $where_stmt = implode(" AND ", $where);
        //$returning = implode(", ", array_merge($fields_clob, $fields_blob));
        $returning = implode(", ", $fields_clob); // array to string to fill 'returning' clause in the sql
        $into_stmt = implode(", ", $into); // array to string to fill 'into' clause in the sql

        $sql = "UPDATE " . static::tableName() . "
                    SET " . $set_stmt . "
                    WHERE " . $where_stmt
        ;
        if ($returning && $into_stmt) {
            $sql .= " RETURNING " . $returning . "
                    INTO " . $into_stmt
            ;
        }
        
        $stmt  = oci_parse($db, $sql);
        $my_lob = [];
        // just see http://www.oracle.com/technology/pub/articles/oracle_php_cookbook/fuecks_lobs.html
        // you'll get it i'm sure
        foreach ($into as $key => $value) {
            $my_lob[$key] = oci_new_descriptor($db, OCI_D_LOB);
            oci_bind_by_name($stmt, $value, $my_lob[$key], -1, OCI_B_CLOB);
            //oci_bind_by_name($stmt, $value, $my_lob[$key], -1, OCI_B_BLOB);
        }
        foreach ($fields_dirty as $name => $value) { // don't use $value in oci_bind_by_name! the link inside this variable is changing
            if (!in_array($name, $fields_clob)) {
                oci_bind_by_name($stmt, ":".$name, $fields_dirty[$name]);
            }
        }
        $result = oci_execute($stmt, OCI_DEFAULT); // or die ("Unable to execute query\n"); //echo oci_error()
        if ($result === false) {
            oci_rollback($db);
            return false;
        }
        if ($exist_fields_dirty_clob) {
            foreach ($fields_clob as $key => $name) {
                //if (!$my_lob[$key]->savefile( Yii::getAlias('@webroot/uploads/') . $model->files[0]->name )) {
                if (!$my_lob[$key]->save($this->$name)) {
                    oci_rollback($db);
                    return false; //die("Unable to update clob\n");
                }
            }
        }
        oci_commit($db);
        //$my_lob[$key]->free();
        oci_free_statement($stmt);
        oci_close($db); // not sure
        
        $changedAttributes = [];
        $oldArrtibutes = $this->getOldAttributes();
        foreach ($fields_dirty as $name => $value) {
            $changedAttributes[$name] = isset($oldArrtibutes[$name]) ? $oldArrtibutes[$name] : null;
            $this->setOldAttribute($name, $value);
        }
        $this->afterSave(false, $changedAttributes);
        
        return $result;
    }
    
    /**
     * Override ActiveRecord insert() method to fix LOB data insertion
     * 
     * @inheritdoc
     */
    public function insert($runValidation = true, $attributes = null)
    {
        //return parent::insert($runValidation, $attributes);
        
        if ($runValidation && !$this->validate($attributes)) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);
            return false;
        }
        
        if (!$this->beforeSave(true)) {
            return false;
        }
        
        $db = static::getDbOciLob()->getDbh();
        //$fields_blob = static::$blob_attributes;
        $fields_clob = static::$clob_attributes; // array with the fields to be updated
        $fields_dirty = $this->getDirtyAttributes($attributes); // changed fields with values
        $fields_dirty_names = array_keys($fields_dirty);
        $exist_fields_dirty_clob = array_intersect(array_keys($fields_dirty), $fields_clob);
        
        $values = [];
        $into = [];
        foreach ($fields_dirty as $name => $value) {
            if (in_array($name, $fields_clob)) {
                $values[] = "EMPTY_CLOB()";
                $into[] = ":" . $name;
            /*} elseif (in_array($name, $fields_blob)) {
                $values[] = "EMPTY_BLOB()";
                $into[] = ":" . $name;*/
            } else {
                $values[] = ":" . $name;
            }
        }
        $fields_stmt  = implode(", ", $fields_dirty_names);
        $values_stmt  = implode(", ", $values);
        //$returning = implode(", ", $fields_clob + $fields_blob);
        $returning = implode(", ", $fields_clob);
        $into_stmt = implode(", ", $into);

        $sql = "INSERT INTO " . static::tableName() . "
                    (" . $fields_stmt . ")
                    VALUES(" . $values_stmt . ")
        ";
        if ($returning && $into_stmt) {
            $sql .= " RETURNING " . $returning . "
                    INTO " . $into_stmt
            ;
        }

        $stmt  = oci_parse($db, $sql);
        $my_lob = [];
        // just see http://www.oracle.com/technology/pub/articles/oracle_php_cookbook/fuecks_lobs.html
        // you'll get it i'm sure
        foreach ($into as $key => $value) {
            $my_lob[$key] = oci_new_descriptor($db, OCI_D_LOB);
            oci_bind_by_name($stmt, $value, $my_lob[$key], -1, OCI_B_CLOB);
            //oci_bind_by_name($stmt, $value, $my_lob[$key], -1, OCI_B_BLOB);
        }
        foreach ($fields_dirty as $name => $value) { // don't use $value in oci_bind_by_name!
            if (!in_array($name, $fields_clob)) {
                oci_bind_by_name($stmt, ":".$name, $fields_dirty[$name]);
            }
        }
        
        $result = oci_execute($stmt, OCI_DEFAULT); //or die ("Unable to execute query\n"); //echo oci_error()
        if ($result === false) {
            oci_rollback($db);
            return false;
        }
        if ($exist_fields_dirty_clob) {
            foreach ($fields_clob as $key => $name) {
                //if (!$my_lob[$key]->savefile( Yii::getAlias('@webroot/uploads/') . $model->files[0]->name )) {
                if (!$my_lob[$key]->save($this->$name)) {
                    oci_rollback($db);
                    return false; //die("Unable to update clob\n");
                }
            }
        }
        oci_commit($db);
        //$my_lob[$key]->free();
        oci_free_statement($stmt);
        oci_close($db); // not sure

        // Set primary key for new record
        $orderBy = [];
        foreach ($this->primaryKeyOciLob as $attribute) {
            $orderBy[$attribute] = SORT_DESC;
        }
        foreach ($this->primaryKeyOciLob as $attribute) {
            $fields_dirty[$attribute] = $this->find()
                ->orderBy($orderBy)
                ->limit(1)
                ->one()
                ->$attribute;
        }
        
        foreach ($fields_dirty as $name => $value) {
            $id = static::getTableSchema()->columns[$name]->phpTypecast($value);
            $this->setAttribute($name, $id);
            $fields_dirty[$name] = $id;
        }

        $changedAttributes = array_fill_keys(array_keys($fields_dirty), null);
        $this->setOldAttributes($fields_dirty);
        $this->afterSave(true, $changedAttributes);
        
        return $result;
    }
    
    // METHOD 3 - completed, does not work, substitutes BLOB
    
    /*public function save($runValidation = true, $attributeNames = null)
    {
        if ($this->getIsNewRecord()) {
            //return $this->insert($runValidation, $attributeNames);
            return $this->insertLob3();
        }

        //return $this->update($runValidation, $attributeNames) !== false;
        return $this->updateLob3();
    }
    
    public function insertLob3()
    {   
        // Set lob fields as lob, because standardly ActiveRecord sets them as long
        $HTML_TEXT = $this->HTML_TEXT;
        static::getDb()->createCommand(
            "insert into " . static::tableName() 
            . " (HTML_TEXT)"
            . " values (:HTML_TEXT)"
        )->bindParam(
                ':HTML_TEXT',
                $HTML_TEXT,
                \PDO::PARAM_LOB
        )->execute();
        //catch (Exception $e) {
            //some actions
            //throw $e;
        //}
    }*/
    
    // METHOD 2 - not completed, not working
    
    /**
     * Override ActiveRecord isertInternal() method to fix LOB data insertion
     * 
     * @inheritdoc
     */
    /*protected function insertInternal($attributes = null)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        //$primaryKeys = static::getDb()->schema->insert(static::tableName(), $values); //changed
        
        // additional code
        $schemaObj = static::getDb()->schema;
        $table = static::tableName();
        $columns = $values;
        
        
        
        // Schema->insert() - begin
        //$command = $schema->db->createCommand()->insert($table, $columns); //changed
        
        //additional code
        $commandObj = $schemaObj->db->createCommand();
        
        
        // Command->insert() - begin
        //$params = []; //changed
        $params = [':HTML_TEXT' => new PdoValue($columns['HTML_TEXT'], \PDO::PARAM_LOB)]; //additional code
        $sql = $commandObj->db->getQueryBuilder()->insert($table, $columns, $params);

        //return $this->setSql($sql)->bindValues($params); //changed
        $command = $commandObj->setSql($sql)->bindValues($params); //additional code
        // Command->insert() - end
        
        
        if (!$command->execute()) { // Maybe the problem is also here???
            //return false; //changed
            $primaryKeys = false; // additional code
        }
        $tableSchema = $schemaObj->getTableSchema($table);
        $result = [];
        foreach ($tableSchema->primaryKey as $name) {
            if ($tableSchema->columns[$name]->autoIncrement) {
                $result[$name] = $schemaObj->getLastInsertID($tableSchema->sequenceName);
                break;
            }

            $result[$name] = isset($columns[$name]) ? $columns[$name] : $tableSchema->columns[$name]->defaultValue;
        }

        //return $result; //changed
        $primaryKeys = isset($primaryKeys) ? $primaryKeys : $result; //additional code
        // Schema->insert() - end
        
        
        
        if ($primaryKeys === false) {
            return false;
        }
        foreach ($primaryKeys as $name => $value) {
            $id = static::getTableSchema()->columns[$name]->phpTypecast($value);
            $this->setAttribute($name, $id);
            $values[$name] = $id;
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }*/
    
    
    // METHOD 1 - not completed, not working
    
    /**
    * CakePHP Clob Behaviour
    * Preparing and inserting the clob values in the database
    *
    * Authors: Bobby Borisov and Nedko Penev
    * @link https://nik.chankov.net/2008/01/03/cakephp-and-oracle-handling-clob-fields/
    */
      
    /*
    * Keep all clob values and makes them null before insert
    */
    /*function beforeSave($insert)
    {
        foreach ($this->clob_attributes as $key => $value) {
            //for cakephp clob fields is 'text'
            //echo (gettype($value).strlen($value)." ");{
            $this->clob_attributes[$key] = $value;
            $this->$key = NULL;
        }
        //return true;
        parent::beforeSave($insert);
    }*/

    /*
    * Updates table with saved clob values
    */
    /*function afterSave($insert, $changedAttributes)
    {
        //get existing db connection
        $db = $this->getDb();
        //id of the record to be upated
        $id = (!$this->id) ? $this->getLastInsertId() : $this->id;     
        $fields = array(); // array with the fields to be updated
        foreach ($this->clob_attributes as $key => $value) {       
            $fields[$key] = $value;
        }
        if (!empty($fields)) {
            $set = array(); // set clause in the sql
            $into = array(); // into clause in the sql
            foreach ($fields as $key => $value) {
                $set[] = $value . " = EMPTY_CLOB()";
                $into[] = ":muclob" . $key;
            }
            $set_stmt  = implode(", ", $set     ); // array to string to fill 'set' clause in the sql
            $returning = implode(", ", $fields  ); // array to string to fill 'returning' clause in the sql
            $into_stmt = implode(", ", $into    ); // array to string to fill 'into' clause in the sql

            $sql = "UPDATE " . $this->model->table . "
                        SET " . $set_stmt . "
                        WHERE ID = " . $id . "
                        RETURNING " . $returning . "
                        INTO " . $into_stmt
            ;   

            $stmt  = OCIParse($db, $sql);
            $cnt = 0;
            // just see http://www.oracle.com/technology/pub/articles/oracle_php_cookbook/fuecks_lobs.html
            // you'll get it i'm sure 
            foreach ($into as $key => $value) {         
                $mylob[$cnt] = OCINewDescriptor($db, OCI_D_LOB);
                OCIBindByName($stmt, $value, $mylob[$cnt++], -1, OCI_B_CLOB);
            }         
            OCIExecute($stmt, OCI_DEFAULT) or die ("Unable to execute query\n");
            $cnt = 0;
            foreach ($fields as $key => $value) {
                if (!$mylob[$cnt++]->save($this->saved[$value])) { 
                    OCIRollback($db);
                    die("Unable to update clob\n");   
                }
            }
        }
        OCICommit($db);
        OCILogOff($db);
        OCIFreeStatement($stmt);
        
        parent::afterSave($insert, $changedAttributes);
    }*/
}
