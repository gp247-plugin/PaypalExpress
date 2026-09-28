<?php
/**
 * Plugin format 1.0
 */
#App\GP247\Plugins\PaypalExpress\AppConfig.php
namespace App\GP247\Plugins\PaypalExpress;

use App\GP247\Plugins\PaypalExpress\Models\ExtensionModel;
use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminHome;
use GP247\Core\ExtensionConfigDefault;
use GP247\Core\Models\AdminMenu;
use Illuminate\Support\Facades\DB;
class AppConfig extends ExtensionConfigDefault
{
    public function __construct()
    {
        //Read config from gp247.json
        $config = file_get_contents(__DIR__.'/gp247.json');
        $config = json_decode($config, true);
    	$this->configGroup = $config['configGroup'];
        $this->configKey = $config['configKey'];
        $this->configCode = $config['configCode'] ?? $this->configKey;
        $this->requireCore = $config['requireCore'] ?? [];
        $this->requireComposerPackages = $config['requireComposerPackages'] ?? [];
        $this->requireGp247Extensions = $config['requireGp247Extensions'] ?? [];
        //Path
        $this->appPath = $this->configGroup . '/' . $this->configKey;
        //Language
        $this->title = trans($this->appPath.'::lang.title');
        //Image logo or thumb
        $this->image = $this->appPath.'/'.$config['image'];
        //
        $this->version = $config['version'];
        $this->auth = $config['auth'];
        $this->link = $config['link'];
    }

    public function install()
    {
        $check = AdminConfig::where('key', $this->configKey)
            ->where('group', $this->configGroup)->first();
        if ($check) {
            //Check Plugin key exist
            $return = ['error' => 1, 'msg' =>  gp247_language_render('admin.extension.plugin_exist')];
        } else {
            // Insert plugin to config. WHY: every row must carry the same column
            // set — AdminConfig::insert() derives the column list from the first
            // row, so the credential rows appended below (which add `security`)
            // would otherwise mismatch. Keep `security => 0` on these base rows.
            $dataInsert = [
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configCode,
                    'key'    => $this->configKey,
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => self::ON, //Enable extension
                    'security' => 0,
                    'detail' => $this->appPath.'::lang.title',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_order_status_success',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 2, //Order sttus processing
                    'security' => 0,
                    'detail' => $this->appPath.'::lang.order_status_success',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_order_status_refunded',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 7, //Order sttus refunded
                    'security' => 0,
                    'detail' => $this->appPath.'::lang.order_status_refunded',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_payment_status_success',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 3, //Order payment paid
                    'security' => 0,
                    'detail' => $this->appPath.'::lang.payment_status_success',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_payment_status_refunded',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 4, //Order payment refunded
                    'security' => 0,
                    'detail' => $this->appPath.'::lang.payment_status_refunded',
                ],
            ];

            // Connection credentials (GLOBAL, empty by default; secrets flagged for
            // at-rest encryption). Site owner enters them in admin; the update() hook
            // imports any existing .env values on upgrade (ADR paypal-express_per-store-credentials).
            foreach ($this->credentialSeed() as $row) {
                $dataInsert[] = $row;
            }
            try {
                AdminConfig::insert(
                    $dataInsert
                );
                (new ExtensionModel)->installExtension();
                $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.install_success')];
            } catch (\Throwable $e) {
                $return = ['error' => 1, 'msg' => $e->getMessage()];
            }
 
            
            // Insert menu (skip if already present - insertOrIgnore only dedupes
            // on a DB unique constraint, which this table doesn't have on uri)
            $checkIdBlockPayment = AdminMenu::where('key', 'ADMIN_SHOP_PAYMENT')->first();
            if ($checkIdBlockPayment && !AdminMenu::where('uri', 'admin::paypal-express')->exists()) {
                $menu = [
                    [
                        'parent_id' => $checkIdBlockPayment->id,
                        'sort' => 30,
                        'title' => 'Paypal Express',
                        'icon' => 'fas fa-user',
                        'uri' => 'admin::paypal-express',
                        'key' => null,
                        'type' => 0
                    ]
                ];
                AdminMenu::insertOrIgnore($menu);
            }
        }

        return $return;
    }

    /**
     * The credential rows to seed at GLOBAL (empty; secrets flagged security = 1).
     *
     * @return array<int, array<string, mixed>>
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-per-store-credentials
     * @aidlc-adr paypal-express_per-store-credentials
     */
    private function credentialSeed(): array
    {
        $keys = [
            'sandbox' => ['default' => '1', 'secret' => false],
            'client_id_sandbox' => ['default' => '', 'secret' => false],
            'client_secret_sandbox' => ['default' => '', 'secret' => true],
            'client_id_live' => ['default' => '', 'secret' => false],
            'client_secret_live' => ['default' => '', 'secret' => true],
            'webhook_id' => ['default' => '', 'secret' => false],
        ];

        $rows = [];
        foreach ($keys as $key => $meta) {
            $rows[] = [
                'group'    => $this->configGroup,
                'code'     => $this->configKey.'_config',
                'key'      => $this->configKey.'_'.$key,
                'sort'     => 0,
                'store_id' => GP247_STORE_ID_GLOBAL,
                'value'    => $meta['default'],
                'security' => $meta['secret'] ? 1 : 0,
                'detail'   => $this->appPath.'::lang.'.$key,
            ];
        }

        return $rows;
    }

    /**
     * Upgrade hook: on installs made before 3.1 the credentials lived only in .env.
     * Seed the credential rows if missing, then import any real value still set in .env
     * into the GLOBAL row (secrets encrypted at rest via setConfigValue), so an existing
     * site keeps working without re-entering credentials. From 3.1 the runtime no longer
     * reads .env (config.php dropped env()), so this reads env() DIRECTLY rather than
     * through config(). Idempotent: only fills a missing/empty row, never overwrites a
     * value the admin already saved, and never deletes .env.
     *
     * Best-effort: env() returns nothing when the site runs `config:cache` (Laravel skips
     * loading .env then); such a site re-enters credentials in admin. .env is left intact.
     *
     * @param string|null $fromVersion Version installed before this update.
     * @return array{error:int,msg:string}
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-per-store-credentials
     * @aidlc-adr paypal-express_per-store-credentials
     */
    public function update(?string $fromVersion = null)
    {
        try {
            foreach ($this->credentialSeed() as $row) {
                $existing = AdminConfig::where('group', $this->configGroup)
                    ->where('key', $row['key'])
                    ->where('store_id', GP247_STORE_ID_GLOBAL)
                    ->first();

                // Seed the row if a pre-3.1 install never had it.
                if ($existing === null) {
                    AdminConfig::insert($row);
                    $existing = AdminConfig::where('group', $this->configGroup)
                        ->where('key', $row['key'])
                        ->where('store_id', GP247_STORE_ID_GLOBAL)
                        ->first();
                }

                // Import from .env only when the DB row is still empty (never clobber a saved value).
                if ($existing !== null && (string) $existing->getRawOriginal('value') === '') {
                    $shortKey = substr($row['key'], strlen($this->configKey.'_'));
                    $envValue = env('PAYPAL_'.strtoupper($shortKey));
                    if ($envValue !== null && (string) $envValue !== '') {
                        AdminConfig::setConfigValue(
                            $this->configGroup,
                            $row['key'],
                            GP247_STORE_ID_GLOBAL,
                            (string) $envValue,
                            (int) $row['security']
                        );
                    }
                }
            }

            $return = ['error' => 0, 'msg' => ''];
        } catch (\Throwable $e) {
            $return = ['error' => 1, 'msg' => $e->getMessage()];
        }

        return $return;
    }

    public function uninstall()
    {
        //Please delete all values inserted in the installation step
        try {
            // WHY group + key prefix: per-store overrides saved from the admin screen carry
            // `code = Plugins` (core's setConfigValue), so deleting by `code` alone left the
            // store rows - encrypted secrets included - behind after uninstall.
            (new AdminConfig)
            ->where('group', $this->configGroup)
            ->where(function ($query) {
                $query->where('key', $this->configKey)
                    ->orWhere('key', 'like', $this->configKey.'\_%');
            })
            ->delete();

            //Admin config home
            AdminHome::where('extension', $this->appPath)->delete();

            (new ExtensionModel)->uninstallExtension();

            $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.uninstall_success')];
        } catch (\Throwable $e) {
            $return = ['error' => 1, 'msg' => $e->getMessage()];
        }

        //Delete menu
        AdminMenu::where('uri', 'admin::paypal-express')->delete();

        return $return;
    }
    
    public function enable()
    {
        $process = (new AdminConfig)
            ->where('group', $this->configGroup)
            ->where('key', $this->configKey)
            ->update(['value' => self::ON]);
        //Admin config home
        AdminHome::where('extension', $this->appPath)->update(['status' => 1]);

        if (!$process) {
            $return = ['error' => 1, 'msg' => gp247_language_render('admin.extension.action_error', ['action' => 'Enable'])];
        }
        $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.enable_success')];
        return $return;
    }

    public function disable()
    {
        $return = ['error' => 0, 'msg' => ''];
        $process = (new AdminConfig)
            ->where('group', $this->configGroup)
            ->where('key', $this->configKey)
            ->update(['value' => self::OFF]);
        if (!$process) {
            $return = ['error' => 1, 'msg' => 'Error disable'];
        }

        //Admin config home
        AdminHome::where('extension', $this->appPath)->update(['status' => 0]);

        return $return;
    }


    // Remove setup for store

    public function removeStore($storeId = null)
    {
        // code here
    }

    // Setup for store

    public function setupStore($storeId = null)
    {
       // code here
    }


    // Process when click button plugin in admin    
    
    public function clickApp()
    {
        return redirect(gp247_route_admin('admin_paypal-express.index'));
    }

    /**
     * Get info plugin
     *
     * @return  [type]  [return description]
     */
    public function getInfo()
    {
        $arrData = [
            'title' => $this->title,
            'key' => $this->configKey,
            'code' => $this->configCode,
            'image' => $this->image,
            'permission' => self::ALLOW,
            'version' => $this->version,
            'auth' => $this->auth,
            'link' => $this->link,
            'value' => 0, // this return need for plugin shipping
            'appPath' => $this->appPath
        ];

        return $arrData;
    }

}
