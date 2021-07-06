<?php

namespace FRUnitTest\Captcha;

use PHPUnit\Framework\TestCase;
use FR\Captcha\Captcha;

class CaptchaTest extends TestCase
{
    protected static $CaptchaTestAsset;
    protected static $ServiceResponse;
    protected static $CaptchaStorage;
    protected static $Captcha;
    protected static $config;

    /**
     * This method is executed only once per class
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$CaptchaTestAsset = new CaptchaTestAsset();

        self::$ServiceResponse = self::$CaptchaTestAsset->getServiceResponse();
        self::$CaptchaStorage = self::$CaptchaTestAsset->getCaptchaStorage();
        self::$Captcha = self::$CaptchaTestAsset->getCaptcha();
        self::$config = self::$CaptchaTestAsset->getConfig();
    }

    /**
     * @test
     * @covers FR\Captcha\Captcha::__construct
     * 
     * @return void
     */
    public function constructor()
    {
        // validate captcha_lifetime
        $invalid_captcha_lifetime = ['', '80'];
        foreach ($invalid_captcha_lifetime as $i => $captcha_lifetime) {
            $exception = false;
            try {
                // Override $config with invalid value
                $config = self::$config;
                $config['captcha_lifetime'] = $captcha_lifetime;

                new Captcha(
                    self::$ServiceResponse,
                    $config,
                    self::$CaptchaStorage
                );
            } catch (\Exception $expected) {
                $exception = true;
            }
            $this->assertTrue($exception, 'Exception not thrown: `captcha_lifetime` cannot be empty and must be integer');
        }

        // validate captcha_code_length
        $invalid_captcha_code_length = ['', '80', 2, 11];
        foreach ($invalid_captcha_code_length as $i => $captcha_code_length) {
            $exception = false;
            try {
                // Override $config with invalid value
                $config = self::$config;
                $config['captcha_code_length'] = $captcha_code_length;

                new Captcha(
                    self::$ServiceResponse,
                    $config,
                    self::$CaptchaStorage
                );
            } catch (\Exception $expected) {
                $exception = true;
            }
            $this->assertTrue($exception, 'Exception not thrown: `captcha_code_length` cannot be empty and must be integer and must be from 4 to 10 chars inclusive');
        }

        // validate captcha_image_base_path
        $invalid_captcha_image_base_path = ['', true, '/dir/not/exists', TEST_FR_CAPTCHA_BASE_PATH . '/README.md'];
        foreach ($invalid_captcha_image_base_path as $i => $captcha_image_base_path) {
            $exception = false;
            try {
                // Override $config with invalid value
                $config = self::$config;
                $config['captcha_image_base_path'] = $captcha_image_base_path;

                new Captcha(
                    self::$ServiceResponse,
                    $config,
                    self::$CaptchaStorage
                );
            } catch (\Exception $expected) {
                $exception = true;
            }

            if (in_array($i, [0, 1]))
                $this->assertTrue($exception, 'Exception not thrown: `captcha_image_base_path` cannot be empty and must be string');
            if (in_array($i, [2, 3]))
                $this->assertTrue($exception, 'Exception not thrown: invalid `captcha_image_base_path` must be dir and must exists with writable permissions');
        }

        // validate captcha_image_base_url
        $invalid_captcha_image_base_url = ['', true, 'http//invalid-url'];
        foreach ($invalid_captcha_image_base_url as $i => $captcha_image_base_url) {
            $exception = false;
            try {
                // Override $config with invalid value
                $config = self::$config;
                $config['captcha_image_base_url'] = $captcha_image_base_url;

                new Captcha(
                    self::$ServiceResponse,
                    $config,
                    self::$CaptchaStorage
                );
            } catch (\Exception $expected) {
                $exception = true;
            }
            $this->assertTrue($exception, 'Exception not thrown: `captcha_image_base_url` cannot be empty and must be string and must start with http:// or https://');
        }
    }

    /**
     * @test
     * @covers FR\Captcha\Captcha::generateUniqueId
     * 
     * @return void
     */
    public function generateUniqueId()
    {
        $test = [
            [
                'length' => 32,
            ],
            [
                'length' => '64',
            ],
            [
                'length' => 128,
            ],
            [
                'length' => '256',
            ],
        ];

        foreach ($test as $i => $param) {
            $token = invokeMethod(
                self::$Captcha,
                'generateUniqueId',
                [$param['length']]
            );

            $this->assertEquals($param['length'], strlen($token));
        }
    }

    /**
     * @test
     * @covers FR\Captcha\Captcha::createCaptcha
     * 
     * @return void
     */
    public function createCaptcha()
    {
        $response =  invokeMethod(
            self::$Captcha,
            'createCaptcha',
            []
        );

        $this->assertEquals(200, @$response['code']);
        $this->assertEquals('success', @$response['status']);
        $this->assertIsArray(@$response['data']);
        $this->assertNotEmpty(@$response['data']);
        $this->assertArrayHasKey('captcha_id', @$response['data']);
        $this->assertArrayHasKey('captcha_image_url', @$response['data']);
        $captcha_image_file_path = self::$config['captcha_image_base_path'] . '/' . basename($response['data']['captcha_image_url']);
        $this->assertFileExists($captcha_image_file_path);

        // Test created captcha must be valid
        $row = self::$CaptchaStorage->getCaptchaById(@$response['data']['captcha_id']);
        $this->assertEquals(self::$config['captcha_code_length'], strlen($row['captcha_code']));
        $this->assertTrue((time() < strtotime($row['captcha_expired_at'])));

        // Clear database once tested
        self::$CaptchaStorage->deleteCaptchaById(@$response['data']['captcha_id']);

        return $row['captcha_image'];
    }

    /**
     * @test
     * @covers FR\Captcha\Captcha::removeCaptchaImageFile
     * @depends createCaptcha
     * 
     * @return void
     */
    public function removeCaptchaImageFile($captcha_image)
    {
        invokeMethod(
            self::$Captcha,
            'removeCaptchaImageFile',
            [$captcha_image]
        );

        $captcha_image_file_path = self::$config['captcha_image_base_path'] . '/' . $captcha_image;
        $this->assertFileNotExists($captcha_image_file_path);
    }

    /**
     * @test
     * @covers FR\Captcha\Captcha::verifyCaptcha
     * 
     * @return void
     */
    public function verifyCaptcha()
    {
        // Create expired captcha
        $expired_captcha_id = generateUniqueId(32);
        $expired_captcha_code = '123456';
        self::$CaptchaStorage->insertCaptcha(
            $expired_captcha_id,
            strtolower($expired_captcha_id . '.png'),
            $expired_captcha_code,
            date('Y-m-d H:i:s', strtotime('-1 Second')),
            date('Y-m-d H:i:s')
        );

        // Create valid captcha
        $valid_captcha_id = generateUniqueId(32);
        $valid_captcha_code = 'abC123';
        self::$CaptchaStorage->insertCaptcha(
            $valid_captcha_id,
            strtolower($valid_captcha_id . '.png'),
            $valid_captcha_code,
            date('Y-m-d H:i:s', strtotime('+1 Hour')),
            date('Y-m-d H:i:s')
        );

        $test = [
            [
                'captcha_id' => ' ',
                'captcha_code' => 'not-empty'
            ],
            [
                'captcha_id' => 'not-empty',
                'captcha_code' => ' '
            ],
            [
                'captcha_id' => 'invalid-captcha-id',
                'captcha_code' => 'not-empty'
            ],
            [
                'captcha_id' => $valid_captcha_id,
                'captcha_code' => 'invalid'
            ],
            [
                'captcha_id' => $expired_captcha_id,
                'captcha_code' => '123456'
            ],
            [
                'captcha_id' => $valid_captcha_id,
                'captcha_code' => ' ABC123 '
            ],
        ];

        foreach ($test as $i => $param) {
            $response =  invokeMethod(
                self::$Captcha,
                'verifyCaptcha',
                [
                    $param['captcha_id'],
                    $param['captcha_code']
                ]
            );

            if (in_array($i, [0, 1, 2, 3, 4])) {
                $this->assertEquals(400, @$response['code']);
                $this->assertEquals('error', @$response['status']);
            }
            if (in_array($i, [0]))
                $this->assertEquals('captcha_id_required', @$response['type']);
            if (in_array($i, [1]))
                $this->assertEquals('captcha_code_required', @$response['type']);
            if (in_array($i, [2]))
                $this->assertEquals('captcha_not_found', @$response['type']);
            if (in_array($i, [3]))
                $this->assertEquals('invalid_captcha_code', @$response['type']);
            if (in_array($i, [4]))
                $this->assertEquals('expired_captcha_code', @$response['type']);
            if (in_array($i, [5])) {
                $this->assertEquals(200, @$response['code']);
                $this->assertEquals('success', @$response['status']);
            }
        }

        // Clear database once tested
        self::$CaptchaStorage->deleteCaptchaById($valid_captcha_id);
        self::$CaptchaStorage->deleteCaptchaById($expired_captcha_id);
    }
}
