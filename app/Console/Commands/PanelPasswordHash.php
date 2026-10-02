<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
class PanelPasswordHash extends Command {protected $signature='panel:password-hash {password?}';protected $description='Generate a password hash for PANEL_ADMIN_PASSWORD_HASH';public function handle():int{$p=$this->argument('password')??$this->secret('Password');$this->line(Hash::make($p));return self::SUCCESS;}}
