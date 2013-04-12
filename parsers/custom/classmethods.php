<?php

class Kint_Parsers_ClassMethods extends kintParser
{
    private static $cache = array();

    protected function _parse(&$variable)
    {
        if(!is_object($variable)) {
            return false;
        }

        $className = get_class($variable);

        // Assuming class definition will not change inside one request
        if(!isset(self::$cache[$className])) {
            $reflection = new \ReflectionClass($variable);

            $public = $private = $protected = array();

            // Class methods
            foreach($reflection->getMethods() as $method) {
                $params = array();

                // Access type
                $access = implode(' ', \Reflection::getModifierNames($method->getModifiers()));

                // Method parameters
                foreach($method->getParameters() as $param) {
                    $paramString = '';

                    if($param->isArray()) {
                        $paramString .= 'array ';
                    } elseif($param->getClass()) {
                        $paramString .= $param->getClass()->name . ' ';
                    }

                    $paramString .= ($param->isPassedByReference() ? '&' : '') . '$' . $param->getName();

                    if($param->isDefaultValueAvailable()) {
                        if(is_array($param->getDefaultValue())) {
                            $arrayValues = array();
                            foreach($param->getDefaultValue() as $key => $value) {
                                $arrayValues[] = $key . ' => ' . $value;
                            }

                            $defaultValue = 'array(' . implode(', ', $arrayValues) . ')';
                        } elseif($param->getDefaultValue() === null){
                            $defaultValue = 'NULL';
                        } elseif($param->getDefaultValue() === false){
                            $defaultValue = 'false';
                        } elseif($param->getDefaultValue() === true){
                            $defaultValue = 'true';
                        } elseif($param->getDefaultValue() === ''){
                            $defaultValue = '""';
                        } else {
                            $defaultValue = $param->getDefaultValue();
                        }

                        $paramString .= ' = ' . $defaultValue;
                    }

                    $params[] = $paramString;
                }

                $output = new \kintVariableData();

                // Simple DocBlock parser, look for @return
                if(($docBlock = $method->getDocComment())) {
                    $matches = array();
                    if(preg_match_all('/@(\w+)\s+(.*)\r?\n/m', $docBlock, $matches)) {
                        $lines = array_combine($matches[1], $matches[2]);
                        if(isset($lines['return'])) {
                            $output->operator = '->';
                            $output->type = $lines['return'];
                        }
                    }
                }

                $output->name = ($method->returnsReference() ? '&' : '') . $method->getName() . '(' . implode(', ', $params) . ')';
                $output->access = $access;

                if(is_string($docBlock)) {
                    $lines = array();
                    foreach(explode("\n", $docBlock) as $line) {
                        $line = trim($line);

                        if(in_array($line, array('/**', '/*', '*/'))) {
                            continue;
                        }elseif(strpos($line, '*') === 0) {
                            $line = substr($line, 1);
                        }

                        $lines[] = trim($line);
                    }

                    $output->extendedValue = implode("\n", $lines) . "\n\n";
                }

                $declaringClass = $method->getDeclaringClass();
                $declaringClassName = $declaringClass->getName();

                if($method->isInternal()) {
                    $docName = strtolower($declaringClassName) . '.' . str_replace('__', '', strtolower($method->getName()));

                    $output->extendedValue .= sprintf('<small>PHP manual: <a target="_blank"
                        href="http://www.php.net/manual/en/%s.php">%s</a></small>'."\n", $docName, $docName);
                }

                if($declaringClassName !== $className) {
                    $output->extendedValue .= "<small>Inherited from <i>{$declaringClassName}</i></small>\n";
                }

                $fileName = \Kint::shortenPath($method->getFileName(), $method->getStartLine());

                if($fileName) {
                    $output->extendedValue .= "<small>Defined in {$fileName}</small>";
                }

                $sortName = $access . $method->getName();

                if($method->isPrivate()) {
                    $private[$sortName] = $output;
                } elseif($method->isProtected()) {
                    $protected[$sortName] = $output;
                } else {
                    $public[$sortName] = $output;
                }
            }

            if(!$private && !$protected && !$public) {
                self::$cache[$className] = false;
            }

            ksort($public);
            ksort($protected);
            ksort($private);

            self::$cache[$className] = $public + $protected + $private;
        }

        $this->value = self::$cache[$className];
        $this->type = 'methods';
        $this->size = count(self::$cache[$className]);
    }
}