<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\controllers;

use Craft;
use craft\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Base CP Controller for Spin to Win
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
abstract class BaseCpController extends Controller
{
    /**
     * @var string|null Specific permission required, or null for general access
     */
    protected ?string $requiredPermission = null;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Require CP request
        $this->requireCpRequest();

        // Require base plugin access permission if user is not admin
        $user = Craft::$app->getUser();
        if (!$user->getIsAdmin() && !$user->checkPermission('accessPlugin-spin-to-win')) {
            throw new ForbiddenHttpException('You do not have permission to access Spin to Win.');
        }

        if ($this->requiredPermission !== null && !$user->getIsAdmin()) {
            if (!$user->checkPermission($this->requiredPermission)) {
                throw new ForbiddenHttpException('You do not have permission to perform this action.');
            }
        }

        return true;
    }
}
