<?php
/**
 * Created by PhpStorm.
 * User: langn
 * Date: 21.05.15
 * Time: 09:47
 */

namespace whm\Smoke\Config;

use whm\Smoke\Rules\Html\SizeRule;
use whm\Smoke\Rules\Http\Header\Cache\ExpiresRule;
use whm\Smoke\Rules\Http\Header\Cache\MaxAgeRule;
use whm\Smoke\Rules\Http\Header\Cache\PragmaNoCacheRule;
use whm\Smoke\Rules\Http\Header\GZipRule;
use whm\Smoke\Rules\Http\Header\SuccessStatusRule;
use whm\Smoke\Rules\Html\ClosingHtmlTagRule;
use whm\Smoke\Rules\Http\DurationRule;
use PhmLabs\Components\NamedParameters\NamedParameters;

class Configuration
{

    private $blacklist;
    private $whitelist;

    private $rules = array();

    public function __construct(array $configArray)
    {
        if (array_key_exists("blacklist", $configArray)) {
            $this->blacklist = $configArray["blacklist"];
        } else {
            $this->blacklist = array();
        }

        if (array_key_exists("whitelist", $configArray)) {
            $this->whitelist = $configArray["whitelist"];
        } else {
            $this->whitelist = array("^^");
        }

        if (!array_key_exists("rules", $configArray)) {
            $configArray["rules"] = array();
        }

        $this->initRules($configArray["rules"]);
    }

    public function getBlacklist()
    {
        return $this->blacklist;
    }

    public function getWhitelist()
    {
        return $this->whitelist;
    }

    private function initRules($ruleConfig)
    {
        $rules = array();

        if (count($ruleConfig) == 0) {
            $rules[] = new MaxAgeRule();
            $rules[] = new PragmaNoCacheRule();
            $rules[] = new ExpiresRule();
            $rules[] = new SuccessStatusRule();
            $rules[] = new ClosingHtmlTagRule();
            $rules[] = new DurationRule(1000);
            $rules[] = new SizeRule(200);
            $rules[] = new \whm\Smoke\Rules\Image\SizeRule(50);
            $rules[] = new GZipRule();
        } else {

            foreach ($ruleConfig as $name => $ruleElement) {

                $class = $ruleElement["class"];
                $rule = new $class;

                if (array_key_exists("parameters", $ruleElement)) {
                    if (method_exists($rule, "init")) {

                        $parameters = array();

                        // @todo this must be part of the names parameters library
                        foreach ($ruleElement["parameters"] as $parameter) {
                            foreach ($parameter as $key => $value) {
                                $parameters[$key] = $value;
                            }
                        }

                        NamedParameters::call(array($rule, "init"), $parameters);
                    }
                } else {
                    $rule->init();
                }
                $rules[] = $rule;
            }
        }
        $this->rules = $rules;
    }

    public function getRules()
    {
        return $this->rules;
    }
}