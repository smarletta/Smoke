<?php

namespace whm\Smoke\Rules\Seo;

use whm\Html\Uri;
use whm\Smoke\Http\Response;
use whm\Smoke\Rules\Rule;
use whm\Smoke\Rules\ValidationFailedException;

class GoogleMobileFriendlyRule implements Rule
{
    const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v3beta1/mobileReady?url=#url#&strategy=mobile';

    private function getEndpoint(Uri $uri)
    {
        // return str_replace('#url#', urlencode('http://www.phpgangsta.de'), self::ENDPOINT);
        return str_replace('#url#', urlencode((string) $uri), self::ENDPOINT);
    }

    public function validate(Response $response)
    {
        $uri = $response->getUri();

        $endpoint = $this->getEndpoint($uri);

        $result = json_decode(file_get_contents($endpoint));
        $passResult = $result->ruleGroups->USABILITY;

        if (!$passResult->pass) {
            throw new ValidationFailedException('Google mobile friendly test was not passed. Score ' . $passResult->score . '/100.');
        }
    }
}
