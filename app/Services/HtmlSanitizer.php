<?php
declare(strict_types=1);

namespace App\Services;

final class HtmlSanitizer
{
    private const ALLOWED = '<p><br><h2><h3><h4><strong><em><b><i><ul><ol><li><blockquote><a><figure><figcaption><img><iframe>';
    public static function clean(string $html): string
    {
        $html=strip_tags($html,self::ALLOWED);
        $doc=new \DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach(iterator_to_array($doc->getElementsByTagName('*')) as $node){
            if(in_array($node->tagName,['b','i'],true)){
                $replacement=$doc->createElement($node->tagName==='b'?'strong':'em');
                while($node->firstChild)$replacement->appendChild($node->firstChild);
                $node->parentNode?->replaceChild($replacement,$node);$node=$replacement;
            }
            foreach(iterator_to_array($node->attributes??[]) as $attr){
                $name=strtolower($attr->name);$value=trim($attr->value);
                $allowed=($node->tagName==='a'&&in_array($name,['href','title','target','rel'],true))||($node->tagName==='img'&&in_array($name,['src','alt','width','height','loading'],true))||($node->tagName==='iframe'&&in_array($name,['src','title','width','height','allowfullscreen'],true));
                if(!$allowed||in_array($name,['href','src'],true)&&!preg_match('#^(https?://|/)#i',$value))$node->removeAttribute($name);
            }
            if($node->tagName==='a'&&$node->getAttribute('target')==='_blank')$node->setAttribute('rel','noopener noreferrer');
            if($node->tagName==='iframe'&&!preg_match('#^https://(www\.)?(youtube\.com/embed/|player\.vimeo\.com/video/)#i',$node->getAttribute('src')))$node->parentNode?->removeChild($node);
        }
        $root=$doc->documentElement; $out=''; foreach($root?->childNodes??[] as $child)$out.=$doc->saveHTML($child);
        return $out;
    }
}
