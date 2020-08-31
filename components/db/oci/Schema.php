<?php

namespace app\components\db\oci;

/**
 * Fixes \yii\db\oci\Schema problems when using replication.
 *
 * @author Konstantin Zosimenko <ZosimenkoKV@rncb.ru>
 * @since 2.0
 */
class Schema extends \yii\db\oci\Schema
{
    /**
     * Used to fix work with master-master replication
     * 
     * {@inheritdoc}
     */
    public function init()
    {
        if ($this->defaultSchema === null) {
            $username = ($this->db->masters[0]['username'] ?? $this->db->masterConfig['username'])
                ?? $this->db->username
            ;
            $this->defaultSchema = strtoupper($username);
        }
        parent::init();
    }
    
    /**
     * Returns a value indicating whether a SQL statement is ALTER SESSION.
     * (Used to fix work with master-slave replication)
     * 
     * @param string $sql the SQL statement
     * @return bool whether a SQL statement is ALTER SESSION.
     */
    public function isAlterSessionQuery($sql)
    {
        $pattern = '/^\s*(ALTER SESSION)\b/i';
        return preg_match($pattern, $sql) > 0;
    }
}
