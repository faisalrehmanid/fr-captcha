<?php

namespace FR\Captcha;

use FR\ServiceResponse\ServiceResponseInterface;

/**
 * @author Faisal Rehman <faisalrehmanid@hotmail.com>
 * 
 * This class provide Captcha implementation
 * 
 * Example: How to use this class?
 * 
 * ```
 * <?php
 *      // Provide constructor parameters accordingly
 *      $Captcha = new \FR\Captcha\Captcha();
 * 
 *      // Create Captcha
 *      $ServiceResponse = $Captcha->createCaptcha();
 *      
 *      // Verify Captcha
 *      $ServiceResponse = $Captcha->verifyCaptcha($captcha_id, $captcha_code);
 * 
 *      // Delete all expired captchas
 *      $ServiceResponse = $Captcha->deleteExpiredCaptchas(); 
 * ?>
 * ```
 */
class Captcha
{
    protected $ServiceResponse;
    protected $config;
    protected $CaptchaStorage;

    /**
     * Create Captcha object
     *
     * @param object \FR\ServiceResponse\ServiceResponseInterface $ServiceResponse
     * @param array $config = [  // 1 Hour
     *                           'captcha_lifetime'              => 3600,
     *                           // Length of captcha code
     *                           'captcha_code_length'           => 6,
     *                           // Path to save captcha images
     *                           'captcha_image_base_path'       => '/complete/path/to/store/captcha/images',
     *                           // Base url for captcha images
     *                           'captcha_image_base_url'        => 'https://test.com/captcha/images'];
     * 
     * @param object Storage\CaptchaStorageInterface $CaptchaStorage
     */
    public function __construct(
        ServiceResponseInterface $ServiceResponse,
        array $config,
        Storage\CaptchaStorageInterface $CaptchaStorage
    ) {
        @$captcha_lifetime = $config['captcha_lifetime'];
        @$captcha_code_length = $config['captcha_code_length'];
        @$captcha_image_base_path = $config['captcha_image_base_path'];
        @$captcha_image_base_url = $config['captcha_image_base_url'];

        if (!$captcha_lifetime || !is_int($captcha_lifetime))
            throw new \Exception('`captcha_lifetime` cannot be empty and must be integer');

        if (
            !$captcha_code_length ||
            !is_int($captcha_code_length) ||
            ($captcha_code_length < 4) ||
            ($captcha_code_length > 10)
        )
            throw new \Exception('`captcha_code_length` cannot be empty and must be integer and must be from 4 to 10 chars inclusive');

        if (!$captcha_image_base_path || !is_string($captcha_image_base_path))
            throw new \Exception('`captcha_image_base_path` cannot be empty and must be string');
        if (!is_writable($captcha_image_base_path) || !is_dir($captcha_image_base_path))
            throw new \Exception('invalid `captcha_image_base_path` value is `' . $captcha_image_base_path . '` must be dir and must exists with writable permissions');

        if (
            !$captcha_image_base_url ||
            !is_string($captcha_image_base_url) ||
            (substr($captcha_image_base_url, 0, 7) != 'http://' &&
                substr($captcha_image_base_url, 0, 8) != 'https://')
        )
            throw new \Exception('`captcha_image_base_url` cannot be empty and must be string and must start with http:// or https://');

        $this->ServiceResponse = $ServiceResponse;
        $this->config = $config;
        $this->CaptchaStorage = $CaptchaStorage;
    }

    /**
     * Generate Unique ID of fixed length
     *
     * @param int $length
     * @return string
     */
    protected function generateUniqueId($length)
    {
        $length = intval($length) / 2;

        if (function_exists('random_bytes')) {
            $random = random_bytes($length);
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $random = openssl_random_pseudo_bytes($length);
        }

        if ($random !== false && strlen($random) === $length) {
            return  bin2hex($random);
        }

        $unique_id = '';
        $characters = '0123456789abcdef';
        for ($i = 0; $i < ($length * 2); $i++) {
            $unique_id .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $unique_id;
    }

    /**
     * Generate captcha image
     *
     * @param string $captcha_id
     * @param string $captcha_code
     * @return string file name
     */
    protected function generateCaptchaImage($captcha_id, $captcha_code)
    {
        $count = strlen($captcha_code);
        $image_width = 175;
        $image_height = 50;

        // Initialise image with dimensions in pixels
        $image = @imagecreatetruecolor($image_width, $image_height);

        // Set background to white
        $background = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
        imagefill($image, 0, 0, $background);

        // Draw random lines on canvas
        for ($i = 0; $i < 6; $i++) {
            imagesetthickness($image, rand(1, 3));
            $linecolors = [0xCC, 0xBB, 0xAA];
            $color = $linecolors[rand(0, 2)];
            $linecolor = imagecolorallocate($image, $color, $color, $color);
            imageline($image, 0, rand(0, $image_height), $image_width, rand(0, $image_height), $linecolor);
        }

        // Add captcha code to image
        $x = 6;
        for ($i = 0; $i < $count; $i++) {
            $fontfile = __DIR__ . '/fonts/' . rand(1, 5) . '.ttf';
            $textcolors = [0x00, 0x11, 0x22, 0x33];
            $color = $textcolors[rand(0, 3)];
            $textcolor = imagecolorallocate($image, $color, $color, $color);
            imagettftext($image, rand(18, 20), rand(-15, 15), $x, rand(30, 40), $textcolor, $fontfile, $captcha_code[$i]);
            $x += (($image_width - 12) / $count);
        }

        // Save captcha image
        ob_start();
        imagepng($image);
        imagedestroy($image);
        $data = ob_get_clean();

        $file_name = $captcha_id . '.png';
        $file_path = $this->config['captcha_image_base_path'] . '/' . $file_name;
        file_put_contents($file_path, $data);

        return $file_name;
    }

    /**
     * Remove captcha image file
     *
     * @param string $captcha_image_file_name
     * @return void
     */
    protected function removeCaptchaImageFile($captcha_image_file_name)
    {
        $file_path = $this->config['captcha_image_base_path'] . '/' . $captcha_image_file_name;
        if (is_file($file_path)) {
            unlink($file_path);
        }
    }

    /**
     * Create captcha
     * 
     * @return array of ServiceResponse
     */
    public function createCaptcha()
    {
        $captcha_id = $this->generateUniqueId(32);

        $captcha_code = '';
        $characters = '1A2a3B4b5C6c7D8d9EeFf';
        for ($i = 0; $i < $this->config['captcha_code_length']; $i++) {
            $captcha_code .= $characters[rand(0, strlen($characters) - 1)];
        }

        $captcha_image = $this->generateCaptchaImage($captcha_id, $captcha_code);
        $captcha_expired_at = date('Y-m-d H:i:s', time() + $this->config['captcha_lifetime']);
        $captcha_created_at = date('Y-m-d H:i:s');

        $this->CaptchaStorage->insertCaptcha(
            $captcha_id,
            $captcha_image,
            $captcha_code,
            $captcha_expired_at,
            $captcha_created_at
        );

        $data = [
            'captcha_id' => $captcha_id,
            'captcha_image_url' => $this->config['captcha_image_base_url'] . '/' . $captcha_image
        ];
        return $this->ServiceResponse->success(200, $data)->toArray();
    }

    /**
     * Verify captcha
     *
     * @param string $captcha_id Case insensitive
     * @param string $captcha_code Case insensitive
     * @return array of ServiceResponse
     */
    public function verifyCaptcha($captcha_id, $captcha_code)
    {
        $captcha_id = trim($captcha_id);
        $captcha_code = trim($captcha_code);

        if (!$captcha_id)
            return $this->ServiceResponse->error(400, 'captcha_id_required', 'captcha_id could not be empty')->toArray();
        if (!$captcha_code)
            return $this->ServiceResponse->error(400, 'captcha_code_required', 'captcha_code could not be empty')->toArray();

        $captcha = $this->CaptchaStorage->getCaptchaById($captcha_id);
        if (empty($captcha))
            return $this->ServiceResponse->error(400, 'captcha_not_found', 'Captcha not found')->toArray();

        if (strtolower($captcha_code) != strtolower($captcha['captcha_code']))
            return $this->ServiceResponse->error(400, 'invalid_captcha_code', 'Invalid captcha code')->toArray();

        if (time() > strtotime($captcha['captcha_expired_at']))
            return $this->ServiceResponse->error(400, 'expired_captcha_code', 'Captcha has been expired')->toArray();

        return $this->ServiceResponse->success(200)->toArray();
    }

    /**
     * Delete all expired captchas
     * 
     * @return array of ServiceResponse
     */
    public function deleteExpiredCaptchas()
    {
        $captchas = $this->CaptchaStorage->getExpiredCaptchas();
        foreach ($captchas as $i => $captcha) {
            // Delete captcha image file from server
            $this->removeCaptchaImageFile($captcha['captcha_image']);
        }

        $this->CaptchaStorage->deleteExpiredCaptchas();
        return $this->ServiceResponse->success(200)->toArray();
    }
}
