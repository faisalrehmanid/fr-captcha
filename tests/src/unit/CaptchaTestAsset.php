<?php

namespace FRUnitTest\Captcha;

use PHPUnit\Framework\TestCase;
use FRUnitTest\Captcha\Storage\MySQL\MySQLTestAsset;
use FRUnitTest\Captcha\Storage\Oracle\OracleTestAsset;
use FR\ServiceResponse\ServiceResponse;
use FR\Captcha\Captcha;

class CaptchaTestAsset extends TestCase
{
    public function getServiceResponse()
    {
        $ServiceResponse = new ServiceResponse();
        return $ServiceResponse;
    }

    public function getConfig()
    {
        $config = [
            // 1 Hour
            'captcha_lifetime'              => 3600,
            // Length of captcha code
            'captcha_code_length'           => 6,
            // Path to save captcha images
            'captcha_image_base_path'       => TEST_FR_CAPTCHA_IMAGE_BASE_PATH,
            // Base url for captcha images
            'captcha_image_base_url'        => TEST_FR_CAPTCHA_IMAGE_BASE_URL,
        ];
        return $config;
    }

    public function getCaptchaStorage()
    {
        if (TEST_FR_CAPTCHA_STORAGE == 'Oracle') {
            if (!TEST_FR_CAPTCHA_STORAGE_ORACLE)
                throw new \Exception('TEST_FR_CAPTCHA_STORAGE_ORACLE is not enabled in phpunit.xml');

            $StorageTestAsset =  new OracleTestAsset();
        } else if (TEST_FR_CAPTCHA_STORAGE == 'MySQL') {
            if (!TEST_FR_CAPTCHA_STORAGE_MYSQL)
                throw new \Exception('TEST_FR_CAPTCHA_STORAGE_MYSQL is not enabled in phpunit.xml');

            $StorageTestAsset =  new MySQLTestAsset();
        } else {
            throw new \Exception('Invalid value for TEST_FR_CAPTCHA_STORAGE in phpunit.xml');
        }

        return $StorageTestAsset->getCaptchaStorage();
    }

    public function getCaptcha()
    {
        $Captcha = new Captcha(
            $this->getServiceResponse(),
            $this->getConfig(),
            $this->getCaptchaStorage()
        );
        return $Captcha;
    }
}
