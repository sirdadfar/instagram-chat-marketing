<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('about:project', function(){ $this->info('IG Automate — Professional Instagram Automation Panel'); })->purpose('Display project information');
