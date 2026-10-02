<?php
namespace App\Services\Automation;
use App\Models\Automation;
class KeywordMatcher {
 public function match(Automation $automation,string $text):array {
  $text=$this->normalize($text); $positive=[];$excluded=[];
  foreach($automation->keywords as $k){$ok=$this->matches($text,$this->normalize($k->keyword),$k->match_type); if($ok){if($k->is_excluded)$excluded[]=$k->keyword;else $positive[]=$k->keyword;}}
  $positiveCount=count($positive); $hasPositive=$automation->keywords()->where('is_excluded',false)->exists();
  $passExcluded=count($excluded)===0; $passPositive=!$hasPositive || ($automation->match_mode==='all' ? $positiveCount===$automation->keywords()->where('is_excluded',false)->count() : $positiveCount>0);
  return ['pass'=>$passExcluded&&$passPositive,'matched'=>$positive,'excluded'=>$excluded,'mode'=>$automation->match_mode];
 }
 private function matches(string $text,string $keyword,string $type):bool{return match($type){'exact'=>$text===$keyword,'starts_with'=>str_starts_with($text,$keyword),'ends_with'=>str_ends_with($text,$keyword),'regex'=>(bool)@preg_match($keyword,$text),'contains'=>str_contains($text,$keyword),default=>str_contains($text,$keyword)};}
 public function normalize(string $value):string { $value=mb_strtolower(trim($value)); return str_replace(['ي','ك','ۀ','ة'],['ی','ک','ه','ه'],$value); }
}
