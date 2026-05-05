<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;

/**
 * OtpService
 *
 * Generates and verifies Time-based One-Time Passwords (TOTP).
 * Secrets are stored in the database (user table) so users only need to
 * scan the QR code once — subsequent logins go straight to code entry.
 *
 * Session structure used:
 *   $_SESSION['username'] — set by AuthController, used here to look up the secret.
 */
class OtpService
{
    private TwoFactorAuth $tfa;

    public function __construct(private UserModel $userModel)
    {
        $this->tfa = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'), 'Ticketmaestrix');
    }

    /**
     * Returns true if the user already has a TOTP secret stored in the database.
     * Used by AuthController to decide whether to show the QR setup screen.
     */
    public function hasSecret(string $email): bool
    {
        $user = $this->userModel->findByEmail($email);
        return $user !== null && !empty($user->totp_secret);
    }

    /**
     * Generate a new TOTP secret for a brand-new user, persist it to the database,
     * and return the QR code as a base64 data URI for the <img> tag.
     * Only call this when hasSecret() returned false.
     */
    public function generate(string $label, array $userData): array
    {
        $secret = $this->tfa->createSecret();
        $userData['totp_secret'] = $secret;
        $this->userModel->create($userData);
        return [
            'qr_code' => $this->tfa->getQRCodeImageAsDataUri($label, $secret),
            'secret'  => $secret,
        ];
    }

    /**
     * Generate a TOTP secret for an existing user, update their record,
     * and return the QR code and secret.
     */
    public function generateForExisting(int $userId, string $label): array
    {
        $secret = $this->tfa->createSecret();
        $user = $this->userModel->load($userId);
        $user->totp_secret = $secret;
        $this->userModel->save($user);
        return [
            'qr_code' => $this->tfa->getQRCodeImageAsDataUri($label, $secret),
            'secret'  => $secret,
        ];
    }
    /**
     * Verify a user-supplied code against the secret stored in the database.
     */
    public function verify(int $userId, string $input): bool
    {
        $user = $this->userModel->load($userId);
        if (!$user || empty($user->totp_secret)) {
            return false;
        }

        return $this->tfa->verifyCode($user->totp_secret, $input);
    }

    /**
     * No-op: the secret lives in the database and is reused across logins.
     * Kept so AuthController doesn't need to change.
     */
    public function invalidate(): void {}
}
