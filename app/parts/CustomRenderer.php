<?php
/* Added in 2024. Extracted from renderer class */
/* 2025 moved to file CustomRenderer.php as logic changed. */

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;

class CustomRenderer {
	private $customRenderers = [];
	private $blocks = [];
	public $dotapp;
	public $dotApp;
	public $DotApp;
	
	function __construct ($dotapp=null) {
		$this->dotapp = DotApp::dotApp();
		$this->dotApp = $dotapp;
		$this->DotApp = $dotapp;
	}
	
	/**
     * Registers a new global code processing type.
     * Renderers are applied to the entire HTML output.
     * * Example:
     * $dotapp->customRenderer->addRenderer("smileys", function($code) {
     * return str_replace(":)", "😊", $code);
     * });
     */
	public function addRenderer(string $name,$renderer) {
		if (!is_callable($renderer)) {
			$renderer = $this->dotApp->stringToCallable($renderer);
		}
		if (is_callable($renderer)) {
			$this->customRenderers[$name] = $renderer;
		} else {
			throw new \Exception("Renderrer must be calable or existing controller !");
		}
	}

    public function blocks($name) {
        return $this->blocks[$name] ?? null;
    }

	/**
     * Registry for standard {{ block:name }} tags.
     * Supports String-to-Callable conversion.
     * * Example:
     * $dotapp->customRenderer->addBlock("youtube", "Media:Video@embed");
     * // In template: {{ block:youtube(videoId) }}{{ /block:youtube }}
     */
	public function addBlock(string $name,$blockFn) {
		if (!is_callable($blockFn)) {
			$blockFn = $this->dotApp->stringToCallable($blockFn);
		}
		if (is_callable($blockFn) && strlen(trim($name)) > 0) {
			$this->blocks[$name] = $blockFn;
		}
	}

    public function getRenderer($name) {
        return $this->customRenderers[$name] ?? false;
	}

    // Ak by sme chceli nejaku cast specificky vyrenderovat nejakym custom rendererom...
	/**
     * Manually forces a specific renderer to process a string.
     * * Example:
     * $rawMarkdown = "# Hello World";
     * echo $dotapp->customRenderer->renderWith("markdown", $rawMarkdown);
     */
    public function renderWith($name,$code, ...$args) {
        $renderer = $this->getRenderer($name);
        if (is_callable($renderer)) {
            return $renderer($code, ...$args);
        } else {
            throw new \Exception("Renderer ".$name." does nto exist !");
        }
    }
	
	public function customRenderers() {
		return($this->customRenderers);
	}
}

?>
