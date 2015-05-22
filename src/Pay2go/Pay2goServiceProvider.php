<?php

namespace Pay2go;

use Illuminate\Support\ServiceProvider;

class Pay2goServiceProvider extends ServiceProvider {

	public function register()
    {
		$this->app['pay2go'] = $this->app->share(function($app)
		{
			$pay2go = new Pay2goPaymentClass();
			return $pay2go;
		});
    }

}
?>