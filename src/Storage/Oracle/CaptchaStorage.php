<?php

namespace FR\Captcha\Storage\Oracle;

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
        $this->captcha_table_name = strtoupper($captcha_table_name);
    }

    /**
     * Return SQL script of database structure
     *
     * @return string
     */
    public function getDBStructure()
    {
        $parts   = explode('.', $this->captcha_table_name);
        @$schema = $parts[0];
        @$table  = $parts[1];

        $script = " CREATE TABLE " . $this->captcha_table_name . "
                    (
                      CAPTCHA_ID          RAW(16)                  NOT NULL,
                      CAPTCHA_IMAGE       VARCHAR2(100 CHAR)       NOT NULL,
                      CAPTCHA_CODE        VARCHAR2(10 CHAR)        NOT NULL,
                      CAPTCHA_EXPIRED_AT  DATE                     NOT NULL,
                      CAPTCHA_CREATED_AT  DATE                     NOT NULL
                    );
                    CREATE UNIQUE INDEX " . $this->captcha_table_name . "_PK ON " . $this->captcha_table_name . " (CAPTCHA_ID);
                    ALTER TABLE " . $this->captcha_table_name . " ADD (
                       CONSTRAINT " . $table . "_PK
                       PRIMARY KEY
                       (CAPTCHA_ID)
                       USING INDEX " . $this->captcha_table_name . "_PK
                       ENABLE VALIDATE 
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
        $query = ' SELECT OWNER, 
                          TABLE_NAME 
                    FROM ALL_TABLES
                        WHERE UPPER(OWNER || \'.\' || TABLE_NAME)
                                IN (:CAPTCHA_TABLE_NAME) ';
        $values = [
            ':CAPTCHA_TABLE_NAME' => str_replace('"', '', strtoupper($this->captcha_table_name)),
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
            = " SELECT " . $exp->getUuid('CAPTCHA_ID') . " CAPTCHA_ID,
                            CAPTCHA_IMAGE,
                            CAPTCHA_CODE,
                        " . $exp->getDate("CAPTCHA_EXPIRED_AT") . " CAPTCHA_EXPIRED_AT,
                        " . $exp->getDate("CAPTCHA_CREATED_AT") . " CAPTCHA_CREATED_AT
                FROM    " . $this->captcha_table_name . "
                WHERE   " . $exp->getUuid('CAPTCHA_ID') . " = :CAPTCHA_ID ";
        $values = [
            ':CAPTCHA_ID' => $captcha_id
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
            = " SELECT " . $exp->getUuid('CAPTCHA_ID') . " CAPTCHA_ID,
                            CAPTCHA_IMAGE,
                            CAPTCHA_CODE,
                        " . $exp->getDate("CAPTCHA_EXPIRED_AT") . " CAPTCHA_EXPIRED_AT,
                        " . $exp->getDate("CAPTCHA_CREATED_AT") . " CAPTCHA_CREATED_AT
                FROM    " . $this->captcha_table_name . "
                WHERE   " . $exp->getDate("CAPTCHA_EXPIRED_AT") . " <= :CAPTCHA_EXPIRED_AT ";
        $values = [
            ':CAPTCHA_EXPIRED_AT' => date('Y-m-d H:i:s')
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
        $data['CAPTCHA_ID'] = $exp->setUuid($captcha_id);
        $data['CAPTCHA_IMAGE'] = $captcha_image;
        $data['CAPTCHA_CODE'] = $captcha_code;
        $data['CAPTCHA_EXPIRED_AT'] = $exp->setDate($captcha_expired_at);
        $data['CAPTCHA_CREATED_AT'] = $exp->setDate($captcha_created_at);
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
                   WHERE " . $exp->getUuid('CAPTCHA_ID') . " = :CAPTCHA_ID ";
        $values = [
            ':CAPTCHA_ID' => $captcha_id
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
                    WHERE   " . $exp->getDate("CAPTCHA_EXPIRED_AT") . " <= :CAPTCHA_EXPIRED_AT ";
        $values = [
            ':CAPTCHA_EXPIRED_AT' => date('Y-m-d H:i:s')
        ];
        $this->DB->delete($query, $values);
    }
}
