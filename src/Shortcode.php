<?php
namespace Thunder\Shortcode;

/**
 * @author Tomasz Kowalczyk <tomasz@kowalczyk.cc>
 */
final class Shortcode
    {
    private $name;
    private $parameters;
    private $content;

    public function __construct($name, array $arguments, $content)
        {
        $this->name = $name;
        $this->parameters = $arguments;
        $this->content = $content;
        }

    public function hasContent()
        {
        return $this->content !== null;
        }

    public function getName()
        {
        return $this->name;
        }

    public function getParameters()
        {
        return $this->parameters;
        }

    public function hasParameter($name)
        {
        return array_key_exists($name, $this->parameters);
        }

    public function getParameter($name, $default = null)
        {
        if($this->hasParameter($name))
            {
            return $this->parameters[$name];
            }
        if(null !== $default)
            {
            return $default;
            }

        $msg = 'Shortcode parameter %s not found and no default value was set!';
        throw new \RuntimeException(sprintf($msg, $name));
        }

    public function getContent()
        {
        return $this->content;
        }
    }
