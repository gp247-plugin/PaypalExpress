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
        $this->requirePackages = $config['requirePackages'] ?? [];
        $this->requireExtensions = $config['requireExtensions'] ?? [];
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
            //Insert plugin to config
            $dataInsert = [
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configCode,
                    'key'    => $this->configKey,
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => self::ON, //Enable extension
                    'detail' => $this->appPath.'::lang.title',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_order_status_success',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 2, //Order sttus processing
                    'detail' => $this->appPath.'::lang.order_status_success',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_order_status_refunded',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 7, //Order sttus refunded
                    'detail' => $this->appPath.'::lang.order_status_refunded',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_payment_status_success',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 3, //Order payment paid
                    'detail' => $this->appPath.'::lang.payment_status_success',
                ],
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configKey.'_config',
                    'key'    => $this->configKey.'_payment_status_refunded',
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => 4, //Order payment refunded
                    'detail' => $this->appPath.'::lang.payment_status_refunded',
                ],
            ];
            try {
                AdminConfig::insert(
                    $dataInsert
                );
                (new ExtensionModel)->installExtension();
                $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.install_success')];
            } catch (\Throwable $e) {
                $return = ['error' => 1, 'msg' => $e->getMessage()];
            }
 
            
            // Insert menu
            $checkIdBlockPayment = AdminMenu::where('key', 'ADMIN_SHOP_PAYMENT')->first();
            if ($checkIdBlockPayment) {
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

    public function uninstall()
    {
        //Please delete all values inserted in the installation step
        try {
            (new AdminConfig)
            ->where('key', $this->configKey)
            ->orWhere('code', $this->configKey.'_config')
            ->delete();

            //Admin config home
            AdminHome::where('extension', $this->appPath)->delete();

            (new ExtensionModel)->uninstallExtension();

            $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.uninstall_success')];
        } catch (\Throwable $e) {
            $return = ['error' => 1, 'msg' => $e->getMessage()];
        }

        //Delete menu
        AdminMenu::where('uri', 'admin::paypal')->delete();

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
        return redirect(gp247_route_admin('admin_paypal.index'));
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
