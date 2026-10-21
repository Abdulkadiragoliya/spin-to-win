<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\services;

use Craft;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use abdulkadiragoliya\spintowin\SpinToWin;
use abdulkadiragoliya\spintowin\models\Campaign;
use abdulkadiragoliya\spintowin\models\Segment;
use yii\base\Component;
use DateTime;
use Throwable;

/**
 * Emails Service
 *
 * Handles winner transactional notifications and verification mailings.
 * Supports branded HTML templates and edition gating.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Emails extends Component
{
    /**
     * Generate secure expiring verification token
     *
     * @return string
     */
    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Send transactional winning coupon notification email to customer
     *
     * @param Campaign $campaign
     * @param Segment $segment
     * @param string $recipientEmail
     * @param string $couponCode
     * @param string|null $expiresAtFormatted
     * @param string|null $recipientName
     * @return bool
     */
    public function sendWinnerNotification(
        Campaign $campaign,
        Segment $segment,
        string $recipientEmail,
        string $couponCode,
        ?string $expiresAtFormatted = null,
        ?string $recipientName = null
    ): bool {
        // Feature check: Automated email notifications is a Plus Edition feature
        $plugin = SpinToWin::getInstance();
        if (!$plugin->editions->hasFeature(Editions::FEATURE_EMAIL_DELIVERY)) {
            Craft::info("Automated email notification skipped: requires Plus Edition.", __METHOD__);
            return false;
        }

        try {
            $site = Craft::$app->getSites()->getCurrentSite();
            $siteName = $site->name ?? Craft::$app->getSystemName();
            $siteUrl = UrlHelper::siteUrl();

            $greeting = $recipientName ? "Hi {$recipientName}," : "Hello Lucky Shopper,";
            $subject = Craft::t('spin-to-win', 'You Won! Your {prize} Coupon Code is Here', ['prize' => $segment->label]);

            $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #1e293b;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
        <div style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 36px 24px; text-align: center; color: #ffffff;">
            <div style="font-size: 38px; margin-bottom: 8px;">🎉</div>
            <h1 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Congratulations!</h1>
            <p style="margin: 0; font-size: 15px; opacity: 0.95;">You spun the wheel on {$siteName} and unlocked an exclusive discount!</p>
        </div>
        
        <div style="padding: 32px 24px; text-align: center;">
            <p style="margin: 0 0 16px 0; font-size: 15px; color: #475569; text-align: left;">{$greeting}</p>
            <p style="margin: 0 0 24px 0; font-size: 15px; color: #475569; text-align: left; line-height: 1.5;">
                Thank you for playing our prize wheel. Here is your promotional discount code ready to use at checkout:
            </p>

            <div style="background: #f1f5f9; border: 2px dashed #6366f1; border-radius: 12px; padding: 20px; margin: 24px 0; display: inline-block; width: 85%;">
                <div style="font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">
                    {$segment->label}
                </div>
                <div style="font-size: 26px; font-weight: 800; font-family: 'Courier New', Courier, monospace; letter-spacing: 2px; color: #0f172a;">
                    {$couponCode}
                </div>
HTML;

            if ($expiresAtFormatted) {
                $htmlBody .= <<<HTML
                <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
                    Expires on: <strong>{$expiresAtFormatted}</strong>
                </div>
HTML;
            }

            $htmlBody .= <<<HTML
            </div>

            <div style="margin: 32px 0 16px 0;">
                <a href="{$siteUrl}" style="background: #4f46e5; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 30px; font-weight: 700; font-size: 15px; display: inline-block; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);">
                    Start Shopping & Redeem Now &rarr;
                </a>
            </div>
            
            <p style="font-size: 12px; color: #94a3b8; margin-top: 24px;">
                Enter this code in your shopping cart or checkout to claim your savings. Single-use only.
            </p>
        </div>

        <div style="background: #f8fafc; padding: 16px 24px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
            Sent by {$siteName} • Spin to Win Promotions
        </div>
    </div>
</body>
</html>
HTML;

            $siteSettings = $plugin->getSettings()->getForSite($site->handle ?? null);

            $message = Craft::$app->getMailer()->compose();

            // Apply site-specific sender name and address if configured
            $fromAddress = !empty($siteSettings['emailSenderAddress']) ? $siteSettings['emailSenderAddress'] : (Craft::$app->getProjectConfig()->get('email.fromEmail') ?? null);
            $fromName = !empty($siteSettings['emailSenderName']) ? $siteSettings['emailSenderName'] : $siteName;
            if ($fromAddress) {
                $message->setFrom([$fromAddress => $fromName]);
            }
            if (!empty($siteSettings['emailReplyTo'])) {
                $message->setReplyTo($siteSettings['emailReplyTo']);
            }

            $message->setTo($recipientEmail);
            $message->setSubject($subject);
            $message->setHtmlBody($htmlBody);

            $sent = $message->send();

            if ($sent) {
                // Record event
                $now = Db::prepareDateForDb(new DateTime());
                Craft::$app->getDb()->createCommand()
                    ->insert('{{%spintowin_events}}', [
                        'campaignId' => (int)$campaign->id,
                        'eventType' => 'email_sent',
                        'ipAddress' => 'system',
                        'userAgent' => 'SpinToWin Mailer',
                        'metadata' => json_encode([
                            'email' => $recipientEmail,
                            'couponCode' => $couponCode,
                            'prize' => $segment->label,
                        ]),
                        'dateCreated' => $now,
                    ])
                    ->execute();

                Craft::info("Sent winner email to '{$recipientEmail}' with coupon '{$couponCode}'", __METHOD__);
                return true;
            }

            return false;
        } catch (Throwable $e) {
            Craft::error("Failed to send winner notification email: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }
}
