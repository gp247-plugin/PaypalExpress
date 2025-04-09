<?php
#App\GP247\Plugins\PaypalExpress\Models\ExtensionModel.php
namespace App\GP247\Plugins\PaypalExpress\Models;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtensionModel
{
    public function uninstallExtension()
    {
        Schema::dropIfExists('paypal_webhooks');
    }

    public function installExtension()
    {
        Schema::create('paypal_webhooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_id')->nullable()->index();
            $table->string('event_type');
            $table->string('resource_id')->nullable();
            $table->string('resource_type')->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
            
            // Index for faster lookups
            $table->index('event_type');
            $table->index('resource_id');
            $table->index('status');
        });
    }
    
}
