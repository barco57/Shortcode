<?php
namespace Thunder\Shortcode;

/**
 * @author Tomasz Kowalczyk <tomasz@kowalczyk.cc>
 */
final class Processor implements ProcessorInterface
    {
    private $handlers = array();
    private $extractor;
    private $parser;
    private $defaultHandler;
    private $recursionDepth = null;

    public function __construct(ExtractorInterface $extractor, ParserInterface $parser)
        {
        $this->extractor = $extractor;
        $this->parser = $parser;
        }

    /**
     * Registers handler for given shortcode name.
     *
     * @param string $name
     * @param callable|HandlerInterface $handler
     *
     * @return self
     */
    public function addHandler($name, $handler)
        {
        if(!is_callable($handler) && !$handler instanceof HandlerInterface)
            {
            $msg = 'Shortcode handler must be callable or implement HandlerInterface!';
            throw new \RuntimeException(sprintf($msg));
            }
        if($this->hasHandler($name))
            {
            $msg = 'Cannot register duplicate shortcode handler for %s!';
            throw new \RuntimeException(sprintf($msg, $name));
            }

        $this->handlers[$name] = $handler;

        return $this;
        }

    /**
     * Registers handler alias for given shortcode name, which means that
     * handler for target name will be called when alias is found.
     *
     * @param string $alias Alias shortcode name
     * @param string $name Aliased shortcode name
     *
     * @return self
     */
    public function addHandlerAlias($alias, $name)
        {
        $this->addHandler($alias, function(Shortcode $shortcode) use($name) {
            return call_user_func_array($this->getHandler($name), array($shortcode));
            });

        return $this;
        }

    /**
     * Default library behavior is to ignore and return matches of shortcodes
     * without handler just like they were found. With this callable being set,
     * all matched shortcodes without registered handler will be passed to it.
     *
     * @param callable $handler Handler for shortcodes without registered name handler
     */
    public function setDefaultHandler(callable $handler)
        {
        $this->defaultHandler = $handler;
        }

    /**
     * Expects matches sorted by position returned from Extractor. Matches are
     * processed from the last to the first to avoid replace position errors.
     * Edge cases are described in README.
     *
     * @param string $text
     * @param int $level
     *
     * @return string
     */
    public function process($text, $level = 0)
        {
        if(null !== $this->recursionDepth && $level > $this->recursionDepth)
            {
            return $text;
            }

        /** @var $matches Match[] */
        $matches = array_reverse($this->extractor->extract($text));

        foreach($matches as $match)
            {
            $shortcode = $this->parser->parse($match->getString());
            $content = $shortcode->hasContent()
                ? $this->process($shortcode->getContent(), $level + 1)
                : $shortcode->getContent();
            $shortcode = new Shortcode($shortcode->getName(), $shortcode->getParameters(), $content);
            $handler = $this->getHandler($shortcode->getName());
            if($handler)
                {
                $replace = $this->callHandler($handler, $shortcode, $match);
                $text = substr_replace($text, $replace, $match->getPosition(), $match->getLength());
                }
            }

        return $text;
        }

    /**
     * Recursion depth level, null means infinite, any integer greater than or
     * equal to zero sets value.
     *
     * @param int|null $depth
     *
     * @return self
     */
    public function setRecursionDepth($depth)
        {
        if(null !== $depth && !(is_int($depth) && $depth >= 0))
            {
            $msg = 'Recursion depth must be null (infinite) or integer >= 0!';
            throw new \InvalidArgumentException($msg);
            }

        $this->recursionDepth = $depth;

        return $this;
        }

    /**
     * @deprecated Use self::setRecursionDepth() instead
     *
     * @param bool $recursion
     *
     * @return self
     */
    public function setRecursion($recursion)
        {
        return $this->setRecursionDepth($recursion ? null : 0);
        }

    private function callHandler($handler, Shortcode $shortcode, Match $match)
        {
        if($handler instanceof HandlerInterface)
            {
            return $handler->isValid($shortcode)
                ? $handler->handle($shortcode)
                : $match->getString();
            }

        return call_user_func_array($handler, array($shortcode));
        }

    private function getHandler($name)
        {
        return $this->hasHandler($name)
            ? $this->handlers[$name]
            : ($this->defaultHandler ? $this->defaultHandler : null);
        }

    private function hasHandler($name)
        {
        return array_key_exists($name, $this->handlers);
        }
    }
