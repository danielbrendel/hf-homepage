<?php

/*
    Asatru PHP - Migration for PhotoModel
*/

/**
 * This class specifies a migration
 */
class PhotoModel_Migration {
    private $database = null;
    private $connection = null;

    /**
     * Store the PDO connection handle
     * 
     * @param \PDO $pdo The PDO connection handle
     * @return void
     */
    public function __construct($pdo)
    {
        $this->connection = $pdo;
    }

    /**
     * Called when the table shall be created or modified
     * 
     * @return void
     */
    public function up()
    {
        $this->database = new Asatru\Database\Migration('PhotoModel', $this->connection);
        $this->database->drop();
        $this->database->add('id INT NOT NULL AUTO_INCREMENT PRIMARY KEY');
        $this->database->add('ident VARCHAR(512) NOT NULL');
        $this->database->add('slug VARCHAR(512) NOT NULL');
        $this->database->add('title VARCHAR(512) NOT NULL');
        $this->database->add('thumb VARCHAR(1024) NOT NULL');
        $this->database->add('full VARCHAR(1024) NOT NULL');
        $this->database->add('created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        $this->database->create();
    }

    /**
     * Called when the table shall be dropped
     * 
     * @return void
     */
    public function down()
    {
        if ($this->database)
            $this->database->drop();
    }
}