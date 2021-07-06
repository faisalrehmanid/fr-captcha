<?php

namespace FR\Captcha\Storage;

interface CaptchaStorageInterface
{
    /**
     * Get captcha details by captcha_id
     * captcha_id is case insensitive
     *
     * @param string $captcha_id
     * @return array
     */
    public function getCaptchaById($captcha_id);

    /**
     * Get all expired captchas
     *
     * @return array
     */
    public function getExpiredCaptchas();

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
    );

    /**
     * Delete captcha details by captcha_id
     *
     * @param string $captcha_id
     * @return void
     */
    public function deleteCaptchaById($captcha_id);

    /**
     * Delete all expired captchas
     *
     * @return void
     */
    public function deleteExpiredCaptchas();
}
