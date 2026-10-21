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
    protected array|int|bool $allowAnonymous = ['execute', 'eligibility', 'record-view', 'apply-cart'];

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (in_array($action->id, ['execute', 'eligibility', 'record-view', 'apply-cart'], true)) {
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
}
