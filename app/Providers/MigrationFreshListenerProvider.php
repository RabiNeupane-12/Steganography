<?php

namespace App\Providers;

use Event;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\ServiceProvider;


class MigrationFreshListenerProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if ($event->input->hasParameterOption('migrate:fresh'))
            {
                $files = glob(public_path('users/*'));
                
                // Deleting all the files in the list
                foreach($files as $file) {
                    if(is_file($file)) 
                        // Delete the given file
                        unlink($file); 
                }

                $files = glob(public_path('images/*'));
                
                // Deleting all the files in the list
                foreach($files as $file) {
                    if(is_file($file)) 
                        // Delete the given file
                        unlink($file); 
                }
                
            }
        });
    }
}
