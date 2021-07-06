<?php

namespace FR\Captcha\Storage\MySQL;

use FR\Db\DbInterface;
use FR\Captcha\Storage\CaptchaStorageInterface;

class CaptchaStorage implements CaptchaStorageInterface
{
    /**
     * @var object FR\Db\DbInterface
     */
    protected $DB;

    /**
     * Captcha table name Like: schema.table_name
     *
     * @var string
     */
    protected $captcha_table_name;

    /**
     * Captcha storage
     *
     * @param object FR\Db\DbInterface $DB
     * @param string $captcha_table_name Like: schema.table_name
     * @throws \Exception `captcha_table_name` cannot be empty and must be string
     */
    public function __construct(DBInterface $DB, $captcha_table_name)
    {
        if (
            !$captcha_table_name ||
            !is_string($captcha_table_name)
        )
            throw new \Exception('`captcha_table_name` cannot be empty and must be string');

        $parts = explode('.', $captcha_table_name);
        if (count($parts) != 2)
            throw new \Exception('`captcha_table_name` name format must be like: schema.table_name');

        $this->DB = $DB;
        $this->captcha_table_name = strtolower($captcha_table_name);
    }

    /**
     * Return SQL script of database structure
     *
     * @return string
     */
    public function getDBStructure()
    {
        $script = " CREATE TABLE " . $this->captcha_table_name . " (
                        captcha_id 	        BINARY(16)   NOT NULL,
                        captcha_image       VARCHAR(100) NOT NULL,
                        captcha_code        VARCHAR(10)  NOT NULL,	
                        captcha_expired_at  DATETIME     NOT NULL,  
                        captcha_created_at  DATETIME     NOT NULL,
                        PRIMARY KEY (captcha_id)
                    ); ";

        return $script;
    }

    /**
     * Create database structure if already not created
     *
     * @return bool return true when created otherwise false
     */
    public function createDBStructure()
    {
        $query = ' SELECT table_schema, 
                          table_name 
                     FROM information_schema.tables 
                    WHERE LOWER(CONCAT(table_schema, \'.\' ,table_name)) 
                        IN (:captcha_table_name) ';
        $values = [
            ':captcha_table_name' => str_replace('`', '', strtolower($this->captcha_table_name)),
        ];
        $tables = $this->DB->fetchColumn($query, $values);
        if (empty($tables)) {
            $query = $this->getDBStructure();
            $this->DB->importSQL($query);
            return true;
        }

        return false;
    }

    /**
     * Get captcha details by captcha_id
     * captcha_id is case insensitive
     *
     * @param string $captcha_id
     * @return array
     */
    public function getCaptchaById($captcha_id)
    {
        $captcha_id = strtolower($captcha_id);
        $exp = $this->DB->getExpression();

        $query
            = " SELECT " . $exp->getUuid('captcha_id') . " captcha_id,
                            captcha_image,
                            captcha_code,
                        " . $exp->getDate("captcha_expired_at") . " captcha_expired_at,
                        " . $exp->getDate("captcha_created_at") . " captcha_created_at
                FROM    " . $this->captcha_table_name . "
                WHERE   " . $exp->getUuid('captcha_id') . " = :captcha_id ";
        $values = [
            ':captcha_id' => $captcha_id
        ];
        $row = $this->DB->fetchRow($query, $values);
        return $row;
    }

    /**
     * Get all expired captchas
     *
     * @return array
     */
    public function getExpiredCaptchas()
    {
        $exp = $this->DB->getExpression();

        $query
            = " SELECT " . $exp->getUuid('captcha_id') . " captcha_id,
                            captcha_image,
                            captcha_code,
                        " . $exp->getDate("captcha_expired_at") . " captcha_expired_at,
                        " . $exp->getDate("captcha_created_at") . " captcha_created_at
                FROM    " . $this->captcha_table_name . "
                WHERE   " . $exp->getDate("captcha_expired_at") . " <= :captcha_expired_at ";
        $values = [
            ':captcha_expired_at' => date('Y-m-d H:i:s')
        ];
        $rows = $this->DB->fetchRows($query, $values);
        return $rows;
    }

    /**
     * Insert captcha details
     *
     * @param string $captcha_id
     * @param string $captcha_image 
     * @param string $captcha_code
     * @param string $captcha_expired_at  Datetime format: Y-m-d H:i:s
     * @param string $captcha_created_at Datetime format: Y-m-d H:i:s
     * @return void
     */
    public function insertCaptcha(
        $captcha_id,
        $captcha_image,
        $captcha_code,
        $captcha_expired_at,
        $captcha_created_at
    ) {
        if (strlen($captcha_id) != 32)
            throw new \Exception('`captcha_id` must be equal to 32 char');

        $exp = $this->DB->getExpression();

        $data = [];
        $data['captcha_id'] = $exp->setUuid($captcha_id);
        $data['captcha_image'] = $captcha_image;
        $data['captcha_code'] = $captcha_code;
        $data['captcha_expired_at'] = $exp->setDate($captcha_expired_at);
        $data['captcha_created_at'] = $exp->setDate($captcha_created_at);
        $this->DB->insert($this->captcha_table_name, $data);
    }

    /**
     * Delete captcha details by captcha_id
     *
     * @param string $captcha_id
     * @return void
     */
    public function deleteCaptchaById($captcha_id)
    {
        $captcha_id = strtolower($captcha_id);
        $exp = $this->DB->getExpression();

        $query = " DELETE FROM " . $this->captcha_table_name . "
                   WHERE " . $exp->getUuid('captcha_id') . " = :captcha_id ";
        $values = [
            ':captcha_id' => $captcha_id
        ];
        $this->DB->delete($query, $values);
    }

    /**
     * Delete all expired captchas
     *
     * @return void
     */
    public function deleteExpiredCaptchas()
    {
        $exp = $this->DB->getExpression();

        $query = " DELETE FROM " . $this->captcha_table_name . "
                    WHERE   " . $exp->getDate("captcha_expired_at") . " <= :captcha_expired_at ";
        $values = [
            ':captcha_expired_at' => date('Y-m-d H:i:s')
        ];
        $this->DB->delete($query, $values);
    }
}
