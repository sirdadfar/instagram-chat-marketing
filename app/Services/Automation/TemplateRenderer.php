<?php
namespace App\Services\Automation;
class TemplateRenderer { public function render(?string $template,array $context):string { $template=(string)$template; $vars=array_merge(['date'=>now()->format('Y-m-d'),'time'=>now()->format('H:i')],$context); return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',function($m)use($vars){$v=data_get($vars,$m[1],$m[0]); return is_scalar($v)?(string)$v:$m[0];},$template)??$template; } }
