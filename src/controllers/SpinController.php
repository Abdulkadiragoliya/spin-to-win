<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\controllers;

use Craft;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use abdulkadiragoliya\spintowin\SpinToWin;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTime;

/**
 * Public Spin Controller
 *
 * Handles storefront visitor spin requests, eligibility checks, and impression events.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class SpinController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = ['execute', 'eligibility', 'record-view', 'apply-cart', 'verify'];

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (in_array($action->id, ['execute', 'eligibility', 'record-view', 'apply-cart', 'verify'], true)) {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Execute a promotional spin (AJAX endpoint)
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionExecute(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $plugin = SpinToWin::getInstance();

        $handle = $request->getBodyParam('campaignHandle');
        $id = $request->getBodyParam('campaignId');

        $campaign = null;
        if ($handle) {
            $campaign = $plugin->campaigns->getCampaignByHandle((string)$handle);
        } elseif ($id) {
            $campaign = $plugin->campaigns->getCampaignById((int)$id);
        }

        if (!$campaign) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('spin-to-win', 'Campaign not found.'),
            ]);
        }

        $email = (string)$request->getBodyParam('email', '');
        $name = (string)$request->getBodyParam('name', '');
        $consent = (bool)$request->getBodyParam('consent', false);

        $result = $plugin->spins->executeSpin($campaign, $email, $name, $consent);

        return $this->asJson($result);
    }

    /**
     * Check visitor spin eligibility before opening wheel
     *
     * @return Response
     */
    public function actionEligibility(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $plugin = SpinToWin::getInstance();

        $handle = (string)$request->getBodyParam('campaignHandle');
        $email = (string)$request->getBodyParam('email');

        $campaign = $plugin->campaigns->getCampaignByHandle($handle);
        if (!$campaign) {
            return $this->asJson(['eligible' => false, 'reason' => 'Campaign not found']);
        }

        $result = $plugin->spins->checkEligibility($campaign, $email);
        return $this->asJson($result);
    }

    /**
     * Track widget impression for conversion analytics
     *
     * @return Response
     */
    public function actionRecordView(): Response
    {
        $request = Craft::$app->getRequest();
        $handle = (string)$request->getBodyParam('campaignHandle');
        $plugin = SpinToWin::getInstance();

        $campaign = $plugin->campaigns->getCampaignByHandle($handle);
        if ($campaign) {
            $plugin->spins->recordImpression((int)$campaign->id);
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * Apply winning coupon code directly to the active Craft Commerce cart
     *
     * @return Response
     */
    public function actionApplyCart(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $code = (string)$request->getBodyParam('couponCode', '');

        if (!$code) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('spin-to-win', 'No coupon code provided.'),
            ]);
        }

        $result = SpinToWin::getInstance()->commerce->applyCouponToActiveCart($code);
        return $this->asJson($result);
    }

    /**
     * Double opt-in verification link endpoint (Pro Edition feature)
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionVerify(): Response
    {
        $token = (string)Craft::$app->getRequest()->getQueryParam('token', '');
        if (empty($token)) {
            throw new BadRequestHttpException('Missing verification token.');
        }

        $spin = (new \craft\db\Query())
            ->from('{{%spintowin_spins}}')
            ->where(['verificationToken' => $token])
            ->one();

        if (!$spin) {
            throw new NotFoundHttpException('Verification token not found or expired.');
        }

        if (!$spin['isVerified']) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%spintowin_spins}}', [
                    'isVerified' => true,
                    'dateUpdated' => Db::prepareDateForDb(new DateTime()),
                ], ['id' => $spin['id']])
                ->execute();
        }

        $campaign = SpinToWin::getInstance()->campaigns->getCampaignById((int)$spin['campaignId']);
        $segment = SpinToWin::getInstance()->segments->getSegmentById((int)$spin['segmentId']);

        // Send winner notification now that email is verified
        if ($campaign && $segment && !empty($spin['couponCode'])) {
            SpinToWin::getInstance()->emails->sendWinnerNotification(
                $campaign,
                $segment,
                $spin['email'],
                $spin['couponCode'],
                null,
                $spin['name']
            );
        }

        $site = Craft::$app->getSites()->getCurrentSite();
        $siteUrl = \craft\helpers\UrlHelper::siteUrl();
        $code = htmlspecialchars($spin['couponCode'] ?? '', ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($segment ? $segment->label : 'Prize', ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified! - {$site->name}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #fff; max-width: 500px; width: 100%; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; text-align: center; padding: 40px 30px; }
        .icon { font-size: 54px; margin-bottom: 16px; }
        h1 { margin: 0 0 10px; font-size: 26px; font-weight: 800; color: #0f172a; }
        p { color: #64748b; font-size: 15px; margin: 0 0 24px; line-height: 1.5; }
        .code-box { background: #f1f5f9; border: 2px dashed #4f46e5; border-radius: 12px; padding: 18px; margin-bottom: 24px; }
        .code-label { font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .code-val { font-size: 28px; font-weight: 800; font-family: monospace; letter-spacing: 2px; color: #0f172a; }
        .btn { display: inline-block; background: #4f46e5; color: #fff; text-decoration: none; font-weight: 700; padding: 14px 32px; border-radius: 30px; font-size: 15px; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🎉</div>
        <h1>Email Verified!</h1>
        <p>Thank you for confirming your email address. Your promotional discount is now unlocked and ready to use!</p>
        <div class="code-box">
            <div class="code-label">{$label}</div>
            <div class="code-val">{$code}</div>
        </div>
        <a href="{$siteUrl}" class="btn">Shop Now & Apply Savings &rarr;</a>
    </div>
</body>
</html>
HTML;

        return $this->asRaw($html);
    }
}
