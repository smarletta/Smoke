<?php

namespace whm\Smoke\Rules\Security;

use Symfony\Component\DomCrawler\Crawler;
use whm\Smoke\Http\Response;
use whm\Smoke\Rules\Rule;
use whm\Smoke\Rules\ValidationFailedException;

class PasswordSecureTransferRule implements Rule
{
    public function validate(Response $response)
    {
        if (!$response->getContentType() === 'text/html') {
            return;
        }

        $crawler = new Crawler($response->getBody());
        $actionNodes = $crawler->filterXPath('//form[//input[@type="password"]]/@action');

        $url = (string) $response->getUri();

        foreach ($actionNodes as $node) {
            $action = $node->nodeValue;

            if (strpos($action, 'https://') === 0) {
                continue;
            }

            if (strpos($url, 'https://') === false) {
                throw new ValidationFailedException('Password is transferred insecure using HTTP.');
            }
        }
    }
}