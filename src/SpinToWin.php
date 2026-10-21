<?php
namespace abdulkadiragoliya\spintowin;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\Cp;
use yii\base\Event;
use abdulkadiragoliya\spintowin\models\Settings;
use abdulkadiragoliya\spintowin\services\Campaigns;
use abdulkadiragoliya\spintowin\services\Segments;
use abdulkadiragoliya\spintowin\services\Spins;
use abdulkadiragoliya\spintowin\services\CommerceBridge;
use abdulkadiragoliya\spintowin\services\Emails;
use abdulkadiragoliya\spintowin\services\Reports;
use abdulkadiragoliya\spintowin\services\Editions;

/**
 * Spin to Win for Craft CMS
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 *
 * @property-read Campaigns $campaigns
 * @property-read Segments $segments
 * @property-read Spins $spins
 * @property-read CommerceBridge $commerce
 * @property-read Emails $emails
 * @property-read Reports $reports
 * @property-read Editions $editions
 * @property-read Settings $settings
 */
class SpinToWin extends Plugin
{
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';
    public const EDITION_PLUS = 'pro'; // Backwards compatibility alias

    /**
     * @var SpinToWin
     */
    public static SpinToWin $plugin;

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    /**
     * Get active edition handle
     *
     * @return string
     */
    public function getEdition(): string
    {
        $edition = $this->edition ?? self::EDITION_LITE;
        if ($edition === 'plus') {
            return self::EDITION_PRO;
        }
        return $edition;
    }

    /**
     * Set active edition handle
     *
     * @param string $edition
     * @return void
     */
    public function setEdition(string $edition): void
    {
        $this->edition = $edition;
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->controllerNamespace = 'abdulkadiragoliya\spintowin\controllers';

        // Register plugin components
        $this->setComponents([
            'campaigns' => Campaigns::class,
            'segments' => Segments::class,
            'spins' => Spins::class,
            'commerce' => CommerceBridge::class,
            'emails' => Emails::class,
            'reports' => Reports::class,
            'editions' => Editions::class,
        ]);

        // Register template roots (CP and Site)
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['spin-to-win'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            }
        );
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['spin-to-win'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            }
        );

        // Register CP URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['spin-to-win'] = 'spin-to-win/dashboard/index';
                $event->rules['spin-to-win/dashboard'] = 'spin-to-win/dashboard/index';
                $event->rules['spin-to-win/campaigns'] = 'spin-to-win/campaigns/index';
                $event->rules['spin-to-win/campaigns/new'] = 'spin-to-win/campaigns/edit';
                $event->rules['spin-to-win/campaigns/<id:\d+>'] = 'spin-to-win/campaigns/edit';
                $event->rules['spin-to-win/reports'] = 'spin-to-win/reports/index';
                $event->rules['spin-to-win/reports/export'] = 'spin-to-win/reports/export';
                $event->rules['spin-to-win/settings'] = 'spin-to-win/settings/index';
            }
        );

        // Register Storefront Site URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['spin-to-win/spin/execute'] = 'spin-to-win/spin/execute';
                $event->rules['spin-to-win/spin/eligibility'] = 'spin-to-win/spin/eligibility';
                $event->rules['spin-to-win/spin/record-view'] = 'spin-to-win/spin/record-view';
                $event->rules['spin-to-win/spin/apply-cart'] = 'spin-to-win/spin/apply-cart';
                $event->rules['spin-to-win/spin/verify'] = 'spin-to-win/spin/verify';
            }
        );

        // Register Commerce Order Completion Listener for coupon redemption tracking
        if (Craft::$app->getPlugins()->isPluginInstalled('commerce')) {
            Event::on(
                \craft\commerce\elements\Order::class,
                \craft\commerce\elements\Order::EVENT_AFTER_COMPLETE_ORDER,
                function (\yii\base\Event $event) {
                    $this->commerce->handleOrderCompleted($event);
                }
            );
        }

        // Register Twig variable: craft.spinToWin
        Event::on(
            \craft\web\twig\variables\CraftVariable::class,
            \craft\web\twig\variables\CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var \craft\web\twig\variables\CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('spinToWin', \abdulkadiragoliya\spintowin\variables\SpinToWinVariable::class);
            }
        );

        // Auto-inject widget on storefront HTML responses if not already manually rendered
        Event::on(
            \craft\web\Response::class,
            \craft\web\Response::EVENT_BEFORE_SEND,
            function (Event $event) {
                /** @var \craft\web\Response $response */
                $response = $event->sender;
                $request = Craft::$app->getRequest();

                if (
                    $request instanceof \craft\web\Request &&
                    $request->getIsSiteRequest() &&
                    !$request->getIsAjax() &&
                    !$request->getIsConsoleRequest() &&
                    $response->isOk &&
                    $response->format === \yii\web\Response::FORMAT_HTML &&
                    is_string($response->data)
                ) {
                    if (strpos($response->data, 'class="stw-widget-root') === false) {
                        $variable = new \abdulkadiragoliya\spintowin\variables\SpinToWinVariable();
                        $widgetHtml = (string)$variable->render();
                        if (!empty($widgetHtml)) {
                            $pos = strripos($response->data, '</body>');
                            if ($pos !== false) {
                                $response->data = substr($response->data, 0, $pos) . "\n" . $widgetHtml . "\n" . substr($response->data, $pos);
                            }
                        }
                    }
                }
            }
        );

        // Register Control Panel navigation
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(
                Cp::class,
                Cp::EVENT_REGISTER_CP_NAV_ITEMS,
                function(RegisterCpNavItemsEvent $event) {
                    $event->navItems[] = [
                        'url' => 'spin-to-win',
                        'label' => Craft::t('spin-to-win', 'Spin to Win'),
                        'icon' => '@abdulkadiragoliya/spintowin/icon.svg',
                    ];
                }
            );
        }

        Craft::info(
            Craft::t('spin-to-win', '{name} plugin loaded ({edition} edition)', [
                'name' => $this->name,
                'edition' => $this->getEdition(),
            ]),
            __METHOD__
        );
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('spin-to-win/settings'));
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('spin-to-win/settings/_plugin-settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'isPro' => $this->editions->isPro(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        if ($item === null) {
            return null;
        }

        $item['subnav'] = [
            'dashboard' => [
                'label' => Craft::t('spin-to-win', 'Dashboard'),
                'url' => 'spin-to-win',
            ],
            'campaigns' => [
                'label' => Craft::t('spin-to-win', 'Campaigns'),
                'url' => 'spin-to-win/campaigns',
            ],
            'reports' => [
                'label' => Craft::t('spin-to-win', 'Reports'),
                'url' => 'spin-to-win/reports',
            ],
            'settings' => [
                'label' => Craft::t('spin-to-win', 'Settings'),
                'url' => 'spin-to-win/settings',
            ],
        ];

        return $item;
    }
}
