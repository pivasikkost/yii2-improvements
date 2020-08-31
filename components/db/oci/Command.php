<?php

namespace app\components\db\oci;

/**
 * Fixes \yii\db\Command problems when using master-slave replication and Oracle database.
 * Allows to use automatic distribution of read and write requests, 
 * since alter session is typically used for read requests
 *
 * @author Konstantin Zosimenko <pivasikkost@gmail.com>
 * @since 2.0
 */
class Command extends \yii\db\Command
{
    /**
     * {@inheritdoc}
     */
    public function prepare($forRead = null)
    {
        $sql = $this->getSql();
        
        if (!$forRead && $this->db->getSchema()->isAlterSessionQuery($sql)) {
            $forRead = true; // use slave
        }
        
        return parent::prepare($forRead);
    }
}
