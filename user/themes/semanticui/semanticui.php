<?php
namespace Grav\Theme;

use Grav\Common\Page\Page;
use Grav\Common\Theme;
use RocketTheme\Toolbox\Event\Event;
use Whoops\Example\Exception;

class SemanticUI extends Theme
{
    public static function getSubscribedEvents() {
        return [
            'onPageContentProcessed' => ['onPageContentProcessed', 0],
        ];
    }

    public function onPageContentProcessed(Event $event) {
        /** @var Page $page */
        $page = $event['page'];
        if (strlen($page->getRawContent()) > 0) {
            try {
                $content = mb_convert_encoding($page->getRawContent(), 'HTML-ENTITIES', 'UTF-8');
                libxml_use_internal_errors(true);
                $dom = new \DOMDocument('1.0', 'utf-8');
                $dom->loadHTML($content);
                foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
                    $nodes = $dom->getElementsByTagName($heading);
                    foreach ($nodes as $node) {
                        /** @var \DOMElement $node */
                        $node->setAttribute('class', 'ui dividing header');
                    }
                }
                foreach (['blockquote'] as $tag) {
                    $nodes = $dom->getElementsByTagName($tag);
                    foreach ($nodes as $node) {
                        /** @var \DOMElement $node */
                        if (!is_null($node) && ($node->attributes->length > 0)) {
                            if (!is_null($node->attributes->getNamedItem('data-instgrm-version'))) {
                                continue;
                            }
                        }
                        $node->setAttribute('class', 'ui testimonial');
                    }
                }
                $html = $dom->saveHTML();
                $html = str_replace('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">', '', $html);
                $html = trim(str_replace(['<html>', '<body>', '</body>', '</html>'], ['', '', '', ''], $html));
                $page->setRawContent($html);
            } catch(Exception $e) {
                var_dump($page->getRawContent());
                die($e);
            }
        }
    }

}
