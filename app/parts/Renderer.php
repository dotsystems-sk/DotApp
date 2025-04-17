<?php

/**
 * Class RENDERER
 * 
 * This class is responsible for rendering the final HTML output in the DotApp framework. 
 * It acts as the template system, processing template files and dynamically generating 
 * the corresponding HTML code based on provided data. 
 * 
 * The renderer class provides functionality for managing layouts, partials, and other components
 * necessary for building a robust templating system within the application. You can also create custom renderers.
 * 
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.6 FREE
 * @license   MIT License
 * @date      2014 - 2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

/*
    Renderer Class Usage:

    Check documentation on https://dotsystems.sk/
*/


namespace Dotsystems\App\Parts;

class Renderer {
	/*
		*
		* @dotapp - Vybrany layout
		*
	*/
	private $dotapp;
	/*
		*
		* @layout - Vybrany layout
		*
	*/
	private $layout = "";
	/*
		*
		* @view - Vybrany VIEW
		*
	*/
	private $view = "";
	/*
		*
		* @viewData - UDAJE, KTORE CHCEM SPRISTUPNIT DO VIEW a LAYOUT
		* Data mozem spristupnit aj priamo vo VIEW. Nemusim ich predat predtym. Ak vobec nejake chcem.
	*/
	private $viewData;
	/*
		*
		* @viewVars - Niekedy mozem potrebovat nie jedno pole s udajmi, ale aby mi do view ci sablony sla premenna s presnym nazvom.
		* Pomocou setViewVar a getViewVar ( alebo getViewVars pre vratenie vsetkych premennych ) je to mozne spravit.
		* Tato premenna sa pouziva na ukladanie udajov.
	*/
	public $viewVars;
	/*
		*
		* @layoutVars - Obdoba ako viewData ale plati pre layouty. 
		Pri renderingu layoutu sa uplatnuju tieto premenne. Pozor, pri renderingu VIEW aj ked je nasledne vlozeny layout ako content,
		uplatnuju sa premenne pre view !!! A teda $viewData
	*/
	public $layoutVars;
	/*
		*
		* @renderedCode - Kod, ktory upravujeme pocas renderingu...
		*
	*/
	private $renderedCode;
	/*
		*
		* @blocks - Callable s blokmi...
		*
	*/
	private $blocks; // Bloky. Mame tu nazvy blokov ak by sme chceli na bloky prepojit CSS alebo JS. Tak tu mame ich nazvy. Vsetkych blokov pouzitych v aktualnom rendereri.
	private $blocks_use; // Ci sa maju pouzivat... 
	/*
		*
		* @useCache - Pouzijeme cache ? + objekt cache
		*
	*/
	private $useCache=false;
	/*
		*
		* @useCssCache - Pouzijeme cache pre CSS ? + objekt cache
		*
	*/
	private $renderedCssFiles;
	/*
		*
		* @renderedCssFiles - Pole so zoznamom vyrenderovanych a minimalizovanych CSS suborov pripojenych do aktualnej sablony CSS
		*
	*/
	private $removeUnusedCss=false;
	/*
		*
		* @removeUnusedCss - Odstranime nepouzite triedy v CSS subore...
		*
	*/
	
	private $cache;
	
	/*
		*
		* @dirl - Potrebujeme vediet v ktorom priecinku su layouty.
		*
	*/
	private $dirl;
	
	/*
		*
		* @dirw - Potrebujeme vediet v ktorom priecinku su views.
		*
	*/
	private $dirw;
	
	/*
		*
		* @custom_renderers - Pole ktore obsahuje callable s vlastnymi rendering funkciami
		*
	*/
	private $custom_renderers;
	
	function __construct($dotapp) {
		$this->module("");
		$this->blocks = array();
		$this->blocks_use = 0;
		$this->dotapp = $dotapp;
		$this->blocks_renderer(1);
    }
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }
	
	public function add_renderer($renderer) {
		if (is_callable($renderer)) {
			$this->custom_renderers[] = $renderer;
		}
	}

	public function add_block($name,$blockFn) {
		if (is_callable($blockFn) && strlen(trim($name)) > 0) {
			$this->blocks[$name] = $blockFn;
		}
	}
	
	public function custom_renderers() {
		return($this->custom_renderers);
	}

	public function blocks_renderer($activate=0) {
		/*
			Block syntax:
			{{ block:block_name(var1,var2) }}Inner Content{{ /block:block_name }}
			{{ block:block_name }}Inner Content{{ /block:block_name }}

			-> call block_function($innerContent,$blockVariables - if defined,$variables - view variables);
		*/
		if ($activate == 0) {
			$this->blocks_use = 0;
			$this->blocks = array();
		} else {

			$this->add_renderer( function($code,$variables) {

				$pattern = '/\{\{\s*block:([\w.-]+)(?:\((.*?)\))?\s*\}\}(.*?)\{\{\s*\/block:\1\s*\}\}/s';

				if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$fullMatch = $match[0];
						$blockName = $match[1];
						$blockVariables = isset($match[2]) && !empty($match[2]) ? explode(',', $match[2]) : [];
						$innerContent = $match[3];

						if (isSet($this->blocks[$blockName])) {
							$replacement = $this->blocks[$blockName]($innerContent,$blockVariables,$variables);
							$code = str_replace($fullMatch,$replacement,$code);
						} else {
							$replacement = "{{ blockerror:".$block." }} Undefined callable function ! {{ /blockerror:".$block." }}";
							$code = str_replace($fullMatch,$replacement,$code);
						}
					}
				}
				return($code);
			});

		}
		return($this);
	}
	
	/*
		*
		* @module($name) - Ak potrebujeme pouzit MODUL a VIEW a template z modulu, tak musime zmenit priecinok...
		$name - nazov modulu teda priecinok s modulom
		*
	*/
	public function module($name) {
		if (strlen($name) > 1) {
			$this->dirl = __ROOTDIR__."/app/modules/".$name."/views/layouts/";
			$this->dirw = __ROOTDIR__."/app/modules/".$name."/views/";
		} else {
			$this->dirl = __ROOTDIR__."/app/parts/views/layouts/";
			$this->dirw = __ROOTDIR__."/app/parts/views/";
		}
		return $this;
	}
	
	public function removeUnusedCss($setting) {
		$this->removeUnusedCss = $setting;
		return $this;
	}
	
	public function useCache($setting) {
		$this->useCache = $setting;
		if ($setting) {
			if (! is_object($this->cache)) $this->cache = new cache($this);
		}
		return $this;
	}
	
	public function useCssCache($setting) {
		$this->useCssCache = $setting;
		if ($setting) {
			if (! is_object($this->cache)) $this->cache = new cache($this);
		}
		return $this;
	}
	
	public function setLayout($layout) {
		$this->layout = $layout;
		if (!isset($this->layoutVars[$this->layout])) $this->layoutVars[$this->layout] = array();
		return $this;
	}
	
	private function getLayout($layout) {
		/*
			LOADNEME LAYOUT					
		*/
		if ($layout != "" && file_exists($this->dirl.$layout.".layout.php")) {
			return(file_get_contents($this->dirl.$layout.".layout.php"));
		} else return("");
	}

	public function setLayoutVar($varname,$data) {
		$this->layoutVars[$this->layout][$varname] = $data;
		return $this;
	}
	
	public function getLayoutVar($varname) {
		if (isset($this->layoutVars[$this->layout][$varname])) {
			return $this->layoutVars[$this->layout][$varname];
		} else {
			return("");
		}
	}
	
	public function getLayoutVars() {
		if (isset($this->layoutVars[$this->layout])) {
			return $this->layoutVars[$this->layout];
		} else {
			return(array());
		}
	}
	
	public function setView($view) {
		$this->view = $view;
		if (!isset($this->viewVars[$this->view])) $this->viewVars[$this->view] = array();
		return $this;
	}

	public function setViewVar($varname,$data) {
		$this->viewVars[$this->view][$varname] = $data;
		return $this;
	}
	
	public function getViewVar($varname) {
		if (isset($this->viewVars[$this->view][$varname])) {
			return $this->viewVars[$this->view][$varname];
		} else {
			return("");
		}
	}
	
	public function getViewVars() {
		if (isset($this->viewVars[$this->view])) {
			return $this->viewVars[$this->view];
		} else {
			return(array());
		}
	}
	
	public function minimizeHTML($html) {
		// Komentare prec
		$html = preg_replace('/<!--(?!<!)[^\[>].*?-->/', '', $html);
		// Medzery meczi tagmi prec
		$html = preg_replace('/>\s+</', '><', $html);
		// Viac medzier ( nie &nbsp; ) do jednej
		$html = preg_replace('/\s+/', ' ', $html);
		// Biele znaky zo zaciatku a konca prec
		$html = trim($html);
	
		return $html;
	}
	
	public function minimizeCSS($css) {
		// Vyhodime komentare
		$css = preg_replace('!/\*.*?\*/!s', '', $css);
		// Vyhodime biele znaky
		$css = preg_replace('/\s*([{}|:;,])\s+/', '$1', $css);
		$css = preg_replace('/\s+!/', '!', $css);
		$css = preg_replace('/;+\}/', '}', $css);
		$css = preg_replace('/\s\s+/', ' ', $css);
		$css = trim($css);
		return $css;
	}

	public function minimizeJS($js) {
		$js = preg_replace('!/\*.*?\*/!s', '', $js);
		$js = preg_replace('/\s*\/\/.*$/m', '', $js);
		$js = preg_replace('/\s*([{}|:;,])\s+/', '$1', $js);
		$js = preg_replace('/\s\s+/', ' ', $js);
		$js = trim($js);
	
		return $js;
	}
	
	public function prepareCss($file,$path,$tagAt,$tagAfter) {
		$sourceCSS = $file;
		$filea = explode("/",$file);
		$filenamesource = end($filea);
		$filenamea = explode(".",$filenamesource);
		/*
			Nazov suboru BEZ pripony...
		*/
		$filename = $filenamea[0];
		$filesrcpath = str_replace($filenamesource,"",$file);
		
		$cachepath = $filesrcpath."cache";
		if (! file_exists($cachepath)) {
			mkdir($cachepath, 0755);
		}

		$savefilename = $filename."_cache_".md5($this->layout).".css";
		
		$phpsavefullpathwithfilename = $cachepath."/".$savefilename;
		
		if ($this->useCssCache == false || ( $this->useCssCache == true && ( !file_exists($phpsavefullpathwithfilename) ) ) ) {
			if (file_exists($sourceCSS)) {
				$csscode = $this->concatCSS($sourceCSS);
				$csscode = $this->minimizeCSS($csscode);
				$this->renderedCssFiles[] = $phpsavefullpathwithfilename;
			} else $csscode = "/* SOURCE CSS FILE '".$sourceCSS."' NOT FOUND */";
			
			/*
				* Ulozime vygenerovany kod do CSS...
			*/
			file_put_contents($phpsavefullpathwithfilename,$csscode);
		}
		
		$path = str_replace("<filename>","cache/".$savefilename,$path);
		$tagAfter = str_replace("<filename>","cache/".$savefilename,$tagAfter);
		
		$vrat = '<link href="'.$path.'" '.$tagAt.'>';
		$vrat .= $tagAfter;
		echo $vrat;
	}
	
	private function concatCSS($sourceCSSfile,$relativePath = '../') {
		/*
			@ $sourceCSSfile - Kompletna cesta k suboru CSS ktory budeme citat
		*/
		/*
			@ $relativePath - Relativna cesta ktorou nahradime ./ pripadne ktoru pridame k ../
			--> zakladna hodnota je ../ kedze cache sa zapisuje do priecinka cache, naspat sa dostaneme s ../
		*/
		// Osetrime oba vstupy proti dvojitym lomkam
		$relativePath = str_replace("//","/",$relativePath);
		$relativePath = str_replace("//","/",$relativePath);
		$relativePath = str_replace("././","./",$relativePath);
		$relativePath = str_replace("././","./",$relativePath);
		
		$sourceCSSfile = str_replace("//","/",$sourceCSSfile);
		$sourceCSSfile = str_replace("//","/",$sourceCSSfile);
		$sourceCSSfile = str_replace("././","./",$sourceCSSfile);
		$relativePath = str_replace("././","./",$relativePath);
		
		// Podelime si cestu k suboru na SUBOR a CESTU k nemu.		
		$sourceCSSfileA = explode("/",$sourceCSSfile);
		$sourceCSSfileName = end($sourceCSSfileA);
		$sourceCSSfilePath = str_replace($sourceCSSfileName,"",$sourceCSSfile);
		$csscode = file_get_contents($sourceCSSfile);
		$csscodeMem = $csscode;
		/*
			* RELATIVNE CESTY V CSS sa musia upravit. A to cesty uzavrete v " " aj v ''
		*/
		$csscode = str_replace('"../',"####999QQQ999###",$csscode);
		$csscode = str_replace('"./','"'.$relativePath,$csscode);
		$csscode = str_replace("####999QQQ999###",'"../'.$relativePath,$csscode);
		
		$csscode = str_replace("'../","####999QQQ999###",$csscode);
		$csscode = str_replace("'./","'".$relativePath,$csscode);
		$csscode = str_replace("####999QQQ999###","'../".$relativePath,$csscode);
		
		$csscode = str_replace("url(./","url(./".$relativePath,$csscode);
		$csscode = str_replace("url(../","url(../".$relativePath,$csscode);
		
		/*
			* @ Vyhladame vsetky IMPORT CSS
		*/
		$prvkyREPLACE = $this->searchBetween($csscode,'@import',';');
		$prvky = $this->searchBetween($csscodeMem,'@import',';');
		foreach ($prvky as $kluc => $prvok) {
			$prvokMem = $prvok;
			$prvok = str_replace('"',"",$prvok);
			$prvok = str_replace("'","",$prvok);
			$cssSubor = trim($prvok);		
			$cssSuborA = explode("/",$cssSubor);
			$cssSuborName = end($cssSuborA);
			$cssSuborPath = str_replace($cssSuborName,"",$cssSubor);
			$csscodeIncluded = $this->concatCSS($sourceCSSfilePath.$cssSubor,$relativePath."/".$cssSuborPath);
			$csscode = str_replace('@import'.$prvkyREPLACE[$kluc].';',$csscodeIncluded,$csscode);
		}
		return($csscode);
	}
	
	private function removeUnusedCssFromView() {
		if (count($this->renderedCssFiles) > 0) {
			$prvky = $this->searchBetween($this->renderedCode,'class="','"');
			$prvky2 = $this->searchBetween($this->renderedCode,"class='","'");
			$prvky = array_merge($prvky,$prvky2);
			$HTMLclasses = array();
			foreach ($prvky as $kluc => $prvok) {
				$classesA = explode(" ",$prvok);
				foreach ($classesA as $class) {
					$class = trim($class);
					if ($class != "") $HTMLclasses[".".$class] = ".".$class;
				}
			}
			foreach ($this->renderedCssFiles as $file) {
				$this->removeUnusedClassesFromCssFile($file,$HTMLclasses);
			}
		}		
	}
	
	private function isArrayPartInString($inputArray,$inputString) {
		foreach ($inputArray as $kluc => $hodnota) {
			if (strpos($inputString,$hodnota) !== false) return(true);
		}
		return(false);
	}
	
	private function removeUnusedClassesFromCssFile($file,$ignoreList) {
		$ignoreList[':root'] = ":root";
		$ignoreList['media'] = "media";
		// Ako hlboko v strome som ( max bude 2 a to pri media query ) ak je 0 som v ROOT css stromu
		$hlbka = 0; 
		
		// Aby som vedel riesit stavy kedy prechadzam do inej hlbky
		$hlbkaMem = 0;
		
		// Aktualna pozicia parsera
		$actualPosition=0;
		
		// Buffer nepreneseny do vysledku
		$actualBuffer="";
		
		// Zapisujem aktualny znak alebo nie...
		$zapis=1;
		
		$outputCss = "";
		
		$startIgnore=0;
		
		$debugprvok = "col";
		$cssCode = " ".file_get_contents($file);
		$cssCode = str_replace("}","} ",$cssCode);
		
		$cssCodea = str_split($cssCode);
		
		foreach ($cssCodea as $znak) {
			$actualBuffer .= $znak;
			
			// Vchadzam dovnutra classy
			if ($znak == "{") {
				$hlbka++;
				
				// som v media query alebo classe
				if ($this->isArrayPartInString($ignoreList,$actualBuffer) || (strpos($actualBuffer,".") === false)) {
					$outputCss .= $actualBuffer;
					$actualBuffer = "";
				} else {
					$startIgnore++;
					$actualBuffer = "";
				}
			}
			
			// Vychadzam z classy
			if ($znak == "}") {
				$hlbka--;				
				if ($startIgnore == 0) {
					$outputCss .= trim($actualBuffer);
					$actualBuffer = "";
				} else {
					$startIgnore--;
					$actualBuffer = "";
				}
				
			}
			
			
			
			$hlbkaMem = $hlbka;
			$actualPosition++;
		}
		
		file_put_contents($file,$outputCss);
	}

	public function renderLayoutCode(callable $renderer = null) {
		$this->renderedCode = "";

		if ( isset($this->layout) && $this->layout != "" ) {
			/*
				Spojime vsetky sablony dokopy
			*/		
			$layoutcode = $this->concatInnerLayouts($this->layout);
		} else {
			$layoutcode = "";
		}

		if ($this->useCache) {
			/*
				* @ - Skusime najst subor v cache
				* Nazov cache suboru je layout nazov plus md5 kodu - Tym zabezpecime ze ak je sablona zmenena vygeneruje sa novy cache
			*/
			$layoutchangedname = str_replace("/","-",$this->layout);
			$layoutchangedname = str_replace("\\","-",$layoutchangedname);
			$viewchangedname = str_replace("/","-",$this->view);
			$viewchangedname = str_replace("\\","-",$viewchangedname);
			
			$cachename = "view_".$viewchangedname."_layout_".$layoutchangedname."_".md5($layoutcode);

			if ($this->cache->cachePageExists($cachename)) {
				$this->renderedCode = $this->cache->cachePageRead($cachename,$this->getViewVars());
				return $this->renderedCode;
			}
		}
		
		/*
			* Nahradime layoutovy kod tym, cim potrebujeme. Nahradime content obsahom view
		*/
		/*
			Umoznime modulom zasah do renderovania. Vhodne je to v pripade ze mame napriklad modul DOT cms. Berme DOT CMS ako priklad v celom vysvetleni.
			Ten ma svoj module.init.php My programujeme modul ktory doplna nejake rendrovacie funkcie. Napriklad pridava galeriu alebo nieco podobne.
			Ale samotny vstavany renderer tuto funkciu nema. Ak by sme tuto funckiu doplnili samostatnym modulom, potom by pre modul DOT CMS nefungoval.
			Bolo by nutne nie len nainstalovat modul s novymi funkciami renderingu ale upravit vo vsetkych parent moduloch ich init. Co by bola hlupost.
			Preto umoznujeme aby dotapp renderer pred odoslanim vyrenderovaneho kodu spustil dalsie renderingy ktore si nejaky modul zadefinuje.
			
			funkciou add_renderer();
			priklad:
				$dotapp->router->renderer->add_renderer( function($code) {
					return $code."<br><br><br>Vytvoril Jozko Pucik";
				});
				
			Tymto dokazeme do kazdeho rendrovaneho kodu pridat na zaver text :)
			
			Takze nam staci ak si vyrobime modul schopny rendrovat nejaku galeriu a samotny modul si zaregistruje vlastny renderer.
			Cim sa automaticky prida funkcia galerie aj pre iny modul naprikald DOT CMS.
		*/
        $render_private_block = function($code) {
			$pattern = '/\{\{\s*privateblock:(.*?)\s*\}\}(.*?)\{\{\s*\/privateblock\s*\}\}/si';
			if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER)) {
				$replacement = '<?php $block = array(); ?>'."\n";
                
				$code = $replacement.$code;
				// $matches contains all matched blocks
				foreach ($matches as $match) {
					$replacement = '<?php $block["'.$match[1].'"] = new \Dotsystems\App\Parts\private_block(base64_decode("'.base64_encode($match[2]).'")); ?>'."\n";
					$code = str_replace($match[0],$replacement,$code);					
				}
			}

			return($code);
		};

		if ($renderer === null) {
			$this->renderedCode = $layoutcode;
            $this->renderedCode = $render_private_block($this->renderedCode);
			foreach ($this->custom_renderers() as $rkey => $custom_renderer) {
				$this->renderedCode = $custom_renderer($this->renderedCode,$this->getLayoutVars());
			}
		} else {
			$this->renderedCode = $renderer($layoutcode);
            $this->renderedCode = $render_private_block($this->renderedCode);
			foreach ($this->custom_renderers() as $rkey => $custom_renderer) {
				$this->renderedCode = $custom_renderer($this->renderedCode,$this->getViewVars());
			}
		}


		//$this->minimizeHTML();
		/*
			Az ked uz mame kod minimalizovany vlozime {{generatorinfo}}
		*/

		if ($this->useCache == true) {
			$this->cache->cachePageSave($cachename,$this->renderedCode);
		}
		
		// Vycistime nepouzite CSS triedy zo suborov
		if ($this->removeUnusedCss == true) $this->removeUnusedCssFromView();

		return($this->renderedCode);
	}

	public function renderLayout() {
		$this->renderLayoutCode();
		$this->updateLayoutContentData();
		
		// Najprv ho prezenieme cez staticky call, cim strati $this pristup
		$this->renderedCode = renderer::phprender_isolated($this->getLayoutVars(),$this->renderedCode);
		return $this->renderedCode;
	}
	
	public function renderViewCode() {
		if (isset($this->view) && $this->view != "") {
			$loadedviewcode = $this->loadView($this->view);
			$loadedviewcode = $this->concatInnerLayouts("",$loadedviewcode);
		
			if ( isset($this->layout) && $this->layout != "" ) {
				$renderer = function ($code) use ($loadedviewcode) {
					return(str_replace("{{ content }}",$code,$loadedviewcode));
				};
				$this->renderLayoutCode($renderer);
				return($this->renderedCode);
			} else {
				$this->renderedCode = $loadedviewcode;
				return($this->renderedCode);
			}
		} else return("");
	}

	public function renderView() {
		$this->renderViewCode();
		$this->updateLayoutContentData();
		
		// Najprv ho prezenieme cez staticky call, cim strati $this pristup
		$this->renderedCode = renderer::phprender_isolated($this->getViewVars(),$this->renderedCode);
		return $this->renderedCode;
	}

	public function phprender_isolated($vars,$code) {
		$isolated_renderer = new renderer_isolator();
		return($isolated_renderer->render($vars,$code));
	}
	
	/*
		$max - Maximalna hlbka vnorenia. Sluzi pre pripad ze by sa generovanie zacyklilo. Ci uz z chyby display_error alebo z toho ze v inner layoute volame sameho seba.
				Takto by sa to cyklilo donekonecna a pre tento pripad mame MAX. Zaroven cislo 20 je maxialne dostacujuce na to aby pokrylo vsetky potreby.
		$actual - aktualna hlbka vnorenia.
	*/
	public function concatInnerLayouts($layout,$code="",$actual=0,$max=20) {
		// Pravdepodobne sme sa zacyklili... Takze ukoncime skript.
		if ($actual == $max ) {
			return("");
		} else {
			$actual++;
		}
		
		if ($code != "") {
			$layoutcode = $code;
		} else {
			$layoutcode = $this->getLayout($layout);
		}
		
		$pattern = 
			/* 	Vnorene layouty
				{{layout:hlavnecasti/navbar.search.small}}
			*/
			'/\{\{\s*layout\s*:\s*([^\}\s]+)\s*\}\}/';
			
		if (preg_match_all($pattern, $layoutcode, $matches)) {
			$found = $matches[1];
			
			foreach ($found as $found_layout) {
				$layoutcode = str_replace('{{ layout:'.$found_layout.' }}',$this->concatInnerLayouts($found_layout,"",$actual,$max),$layoutcode);
			}
		}	
		

		$pattern = 
			/* 	Vnorene baselayouty
				{{ baselayout:hlavnecasti/navbar.search.small }}
			*/
			'/\{\{\s*baselayout\s*:\s*([^\}\s]+)\s*\}\}/';
			
        $remdirl = $this->dirl;
		$remdirw = $this->dirw;
		$this->dirl = __ROOTDIR__."/app/parts/views/layouts/";
		$this->dirw = __ROOTDIR__."/app/parts/views/";
	
		if (preg_match_all($pattern, $layoutcode, $matches)) {
			$found = $matches[1];
			
			foreach ($found as $found_layout) {
				$layoutcode = str_replace('{{ baselayout:'.$found_layout.' }}',$this->concatInnerLayouts($found_layout,"",$actual,$max),$layoutcode);
			}
		}
		
		$this->dirl = $remdirl;
		$this->dirw = $remdirw;
		
		return($layoutcode);
	}

	public function dotBridge($code) {
        /*
            DOT BRIDGE
            Prepojenie PHP s JS.
        */
        $pattern = '/\{\{\s*dotbridge:(\w+|on\((\w+)(?:,(\w+))?\))\s*=\s*"([\w\.]+)(?:\((.*?)\))?"' .
            '(?:\s+(?:' .
            'regenerateId' .
            '|oneTimeUse' .
            '|rateLimit\((\d+),(\d+)\)' . // Zachytáva rateLimit(sekundy,pocet)
            '|internalID="(\d+)"' .
            '|expireAt="(\d+)"' .
            '))*\s*\}\}/';
        preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
        $match_number = 0;
    
        foreach ($matches[0] as $index => $match) {
            $key = $this->dotapp->bridge->generate_id();
            $match_number++;
            $fullMatch = $match[0]; // Celý výraz
            $offset = $match[1];     // Offset výrazu
    
            // Parametre premostenia
            $event = $matches[1][$index][0]; // click, onkeyup a tak ďalej
    
            /*
                Parameters that should be sent from the frontend to this function. These are inputs in the HTML code.
                
                <input type="text" dotbridge="email"> - The value of this input will be sent to the parameter 'email'.
                Values will be sent in $_POST['data']['variable']; - e.g., $_POST['data']['email'];
    
                Example:
                <div class="loginform">
                    <input type="text" dotbridge="email">
                    <input type="password" dotbridge="password">
                    <button {{ dotbridge:on(click)="loginfunction(email, password)" regenerateId oneTimeUse rateLimit(60,10) rateLimit(3600,100) expireAt="14500" internalID="login.1" }}>Login</button>
                    <input type="text" {{ dotbridge:input="newsletter.email(email, 5, 'valid-email', 'invalid-email')" }} {{ dotbridge:on(keyup,"Enter")="cms.newsletter(newsletter.email)" regenerateId oneTimeUse rateLimit(60,10) rateLimit(3600,100) expireAt="14500" internalID="newsletter.1" }}>
                </div>
    
                Parameter explanations:
                - regenerateId: If set, the button ID will change on each click.
                - oneTimeUse: If set, the button can only be clicked once.
                - rateLimit(sekundy,pocet): Sets the number of allowed clicks within a time window (in seconds). Multiple rate limits can be defined, e.g., rateLimit(60,10) allows 10 clicks per minute, rateLimit(3600,100) allows 100 clicks per hour.
                - internalID: Sets the internal ID. Counters will be linked to this ID.
                            If not set, an internal ID is generated from the function name. This allows the rate limit to be enforced by function name.
                - expireAt: Sets a timestamp. If the current time exceeds this timestamp, the button becomes invalid.
    
                - "before" will be a function that displays a loading state...
                - "after" will be a function that processes the output, makes changes, and then hides the loading state...
            */
    
            if ($event == "input") {
                $function = $matches[4][$index][0]; // Názov PHP funkcie
                $params = $matches[5][$index][0] ?? '';
                $params = str_replace("(", "", $params);
                $params = str_replace(")", "", $params);
    
                $paramsa = explode(",", $params);
                $params = [];
                foreach ($paramsa as $key => $value) {
                    $value = trim($value);
                    $value = trim($value, "'");
                    if ($value != "") $params[$key] = $value;
                }
    
                $replacement = "";
                if (count($params) > 0) $replacement .= ' dotbridge-result="0"';
                $replacement .= ' dotbridge-input="' . $function . '"';
                $replacement .= $this->dotapp->bridge->input_filter_run($params);
    
                // Nahradíme kód
                $code = str_replace($fullMatch, $replacement, $code);
            } else {
                $eventkey = "";
                $event = $matches[2][$index][0];
                $eventkey = $matches[3][$index][0];
                $function = $matches[4][$index][0]; // Názov PHP funkcie
                $params = $matches[5][$index][0] ?? '';
                $params = str_replace("(", "", $params);
                $params = str_replace(")", "", $params);
    
                $regenerateId = strpos($fullMatch, "regenerateId") !== false;
                $oneTimeUse = strpos($fullMatch, "oneTimeUse") !== false;
    
                // Spracovanie rate limiterov do poľa $limiters
                $limiters = [];
                foreach ($matches[6] as $rateIndex => $seconds) {
                    if ($seconds[1] !== -1 && $matches[7][$rateIndex][1] !== -1) { // Ak je match validný
                        $limiters[] = [
                            'seconds' => (int)$matches[6][$rateIndex][0], // Sekundy
                            'count' => (int)$matches[7][$rateIndex][0]    // Počet
                        ];
                    }
                }
    
                $internalID = $matches[8][$index][0]; // Internal ID
                if ($internalID == "") $internalID = md5($function);
                $expireAt = $matches[9][$index][0];
                if ($expireAt == "") $expireAt = 0;
    
                // Registrácia listenera s poľom $limiters
                $this->dotapp->bridge->register_listener(
                    $key,
                    $regenerateId,
                    $oneTimeUse,
                    $limiters, // Pole rate limiterov
                    $internalID,
                    $expireAt,
                    $function
                );
    
                // Vytvoríme replacement
                $replacement = "";
                $replacement .= ' dotbridge-key="' . $this->dotapp->dsm->get('_bridge.key') . '"';
                $replacement .= ' dotbridge-id="' . $key . '"';
                $replacement .= ' dotbridge-event="' . $event . '"';
                if ($eventkey != "") $replacement .= ' dotbridge-event-arg="' . $eventkey . '"';
                $replacement .= ' dotbridge-data="' . $this->dotapp->encrypt($function, $key) . '"';
                $replacement .= ' dotbridge-data-id="' . $this->dotapp->encrypt($internalID, $key) . '"';
                $replacement .= ' dotbridge-function="' . $function . '"';
                if ($params != "") $replacement .= ' dotbridge-inputs="' . $params . '"';
    
                // Nahradíme kód
                $code = str_replace($fullMatch, $replacement, $code);
            }
        }
    
        return $code;
    }

	public function  updateLayoutContentData($layoutdata=null) {
	
		$patterns = [
			/*
				Prekladač jazyka
				{{_ var: $variable }} -> '<?php $translator($1); ?>';
				{{_ "" }} -> '<?php $translator("$1"); ?>';
			*/
			'/\{\{\_\s*var:\s*\$(?!_)(\w+(?:\[\'.*?\'\])*)\s*\}\}/' => '<?php echo $translator($$1); ?>',
			'/\{\{\_\s*"([^"]*)"\s*\}\}/' => '<?php echo $translator("$1"); ?>',

			/* Premenné, syntax
			   {{ var: $variableName }}
			*/
			'/\{\{\s*var:\s*\$(?!_)(\w+(?:\[\'.*?\'\])*)\s*\}\}/' => '<?php echo $$1; ?>',

			/* IF podmienka, syntax
			   {{ if $condition }}
				   Nieco sem
			   {{ elseif $otherCondition }}
				   Nieco sem
			   {{ else }}
				   Nieco sem
			   {{ /if }}
			*/
			'/\{\{\s+if\s+(.+?)\s+\}\}/' => '<?php if ($1): ?>',
			'/\{\{\s+elseif\s+(.+?)\s+\}\}/' => '<?php elseif ($1): ?>',
			'/\{\{\s+else\s+\}\}/' => '<?php else: ?>',
			'/\{\{\s+\/if\s+\}\}/' => '<?php endif; ?>',

			/*
			   Foreach priklad:
			   
			   {{ foreach $items as $item }}
				   <li>{{ var: $item }}</li>
				{{ /foreach }}
			*/
			'/\{\{\s*foreach\s+((?:\$\w+|\$\w+\[\'\w+\'\])+(?:\[\'.*?\'\])*(?:\s+as\s+\$\w+))\s*\}\}/' => '<?php foreach ($1): ?>',
			'/\{\{\s+\/foreach\s+\}\}/' => '<?php endforeach; ?>',

			/*
				{{ while $index < count($items) }}
				   <li>{{ var: $items[$index] }}</li>
				   <?php $index++; ?>
			   {{ /while }}
			*/
			'/\{\{\s+while\s+(.+?)\s+\}\}/' => '<?php while ($1): ?>',
			'/\{\{\s+\/while\s+\}\}/' => '<?php endwhile; ?>',
		];

	
        /*
			Nahradime patterny 
		*/
		
		if (isset($layoutdata)) {
            $extracted = $this->extract_code($layoutdata);
			foreach ($patterns as $pattern => $replacement) {
				$layoutdata = preg_replace($pattern, $replacement, $layoutdata);
			}
			$layoutdata = $this->dotBridge($layoutdata);
			return $layoutdata;
		}
		
        $extracted = $this->extract_code($this->renderedCode);
        foreach ($patterns as $pattern => $replacement) {
            $this->renderedCode = preg_replace($pattern, $replacement, $this->renderedCode);
        }
        $this->renderedCode = $this->dotBridge($this->renderedCode);

		return $this;
	}

    private function extract_code($code) {
        $pattern = '/(<\?(php|=)?(?:[^\'"\\\\]|\\\\.|\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*")*?\?>)/s';
    
    // Use preg_split to break the string at PHP tags, keeping the delimiters
    $segments = preg_split($pattern, $code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    // Resulting array
    return $segments;
    }
	
	public function searchBetween($content,$startstr,$endstr) {
		/*
			* Hladame vsetky vyskyty nejakeho sova uzavreteho do inych slov
			* @ napriklad {{layout:hovno}} - vratime slovo "hovno" medzi "{{layout:" a "}}"
		*/
		$navrat = array();
		$startpos = 0;
		$doCycle = 1;
		while ($doCycle == 1 && (($pozicia = strpos($content,$startstr,$startpos)) !== false) ) {
			$startpos = $pozicia + strlen($startstr) + 1;
			if ($startpos > strlen($content)) {
				$doCycle = 0;
			} else {
				$pozicia_end = strpos($content,$endstr,$startpos);
				if ($pozicia_end !== false && $pozicia_end > $startpos) {
					$layoutname = substr($content,$pozicia+strlen($startstr),$pozicia_end-$pozicia-strlen($startstr));
					$navrat[] = $layoutname;
				}
			}			
			$startpos = $pozicia_end+1;
			if ($pozicia_end === false) $doCycle = 0;
		}
		return $navrat;
	}
	
	public function searchBetweenPrecise($content,$startstr,$endstr) {
		/*
			* Hladame vsetky vyskyty nejakeho sova uzavreteho do inych slov
			* @ napriklad {{layout:hovno}} - vratime slovo "hovno" medzi "{{layout:" a "}}"
		*/
		$navrat = array();
		$startpos = 0;
		$doCycle = 1;
		$keyForArray = 0;
		while ($doCycle == 1 && (($pozicia = strpos($content,$startstr,$startpos)) !== false) ) {
			$startpos = $pozicia + strlen($startstr) + 1;
			if ($startpos > strlen($content)) {
				$doCycle = 0;
			} else {
				$pozicia_end = strpos($content,$endstr,$startpos);
				if ($pozicia_end !== false && $pozicia_end > $startpos) {
					$pozicia_checker = strpos($content,$startstr,$startpos);
					if ($pozicia_checker !== false && $pozicia_checker < $pozicia_end && $pozicia_checker > $pozicia) {
						$pozicia = $pozicia_checker;					
					}
					$layoutname = substr($content,$pozicia+strlen($startstr),$pozicia_end-$pozicia-strlen($startstr));
					$navrat['hodnoty'][$keyForArray] = $layoutname;
					$navrat['pozicie'][$keyForArray] = $pozicia;
					$keyForArray++;
				}
			}			
			$startpos = $pozicia_end+1;
			if ($pozicia_end === false) $doCycle = 0;
		}
		return $navrat;
	}
	
	private function removeBetween($text,$start,$end) {
		return preg_replace('/'.$start.'[\s\S]+?'.$end.'/', '', $text);
	}
	
	private function replaceBetween($text,$replaceTo,$start,$end) {
		return preg_replace('/'.$start.'[\s\S]+?'.$end.'/', $replaceTo, $text);
	}
	
	public function loadView($view) {
		return(file_get_contents($this->dirw.$view.".view.php"));
	}
	
	public function loadViewStatic($view) {
		/*
			* @LEN VRATIME VYSTUP Z VIEW - Ak je view staticka stranka
		*/
		ob_start();
			foreach ($this->getViewVars() as $vkey => $vvalue) {
				$$vkey = $vvalue;
			}
			include $this->dirw.$view.".view.php";
		return ob_get_clean();
	}
	
}

class renderer_isolator {
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

	public static function phpsandbox_disabled() {
		$disable = [
			'eval', 'exec', 'system', 'shell_exec', 'passthru', 'proc_open', 'popen', 'pcntl_exec',
			'unlink', 'rmdir', 'rename', 'copy', 'chmod', 'chown', 'chgrp', 'file_get_contents',
			'file_put_contents', 'file', 'fopen', 'fread', 'fwrite', 'fclose', 'fgets', 'fputcsv',
			'file_exists', 'is_readable', 'is_writable', 'is_executable', 'mkdir', 'touch',
			'move_uploaded_file', 'symlink', 'link', 'readfile', 'opendir', 'readdir', 'scandir',
			'dir', 'glob', 'parse_ini_file', 'fileinfo', 'fsockopen', 'pfsockopen', 'curl_exec',
			'curl_multi_exec', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'stream_socket_client',
			'stream_socket_server', 'stream_socket_enable_crypto', 'get_headers', 'socket_create',
			'socket_connect', 'socket_write', 'socket_read', 'socket_recv', 'socket_send', 'phpinfo',
			'getenv', 'putenv', 'get_current_user', 'getmyuid', 'getmypid', 'getmygid', 'getrusage',
			'sys_getloadavg', 'dl', 'pcntl_fork', 'pcntl_signal', 'pcntl_wait', 'pcntl_waitpid',
			'pcntl_wifexited', 'pcntl_wifstopped', 'pcntl_wifsignaled', 'pcntl_wexitstatus',
			'pcntl_wtermsig', 'pcntl_wstopsig', 'pcntl_alarm', 'pcntl_exec', 'pcntl_getpriority',
			'pcntl_setpriority', 'pcntl_sigprocmask', 'pcntl_sigtimedwait', 'pcntl_sigwaitinfo',
			'pcntl_strerror', 'pcntl_unshare', 'create_function', 'call_user_func', 'call_user_func_array',
			'register_shutdown_function', 'register_tick_function', 'mail', 'header', 'headers_list',
			'headers_sent', 'extract', 'parse_str', 'http_response_code'
		];
		return($disable);
	}

	private function sanitizePHP($code) {
		$pattern = '/\b(' . implode('|', SELF::phpsandbox_disabled()) . ')\b\s*\(/i';
		return preg_replace($pattern, '', $code);
	}
	
	/*
		Pouzijeme vstavany PHP tokenizer aby sme zistili ktore funkcie sa pouzivaju a nebezpecne odstranili 
	*/
	private function sanitizeMIXED($html) {
		$tokens = token_get_all($html);
		$sanitizedContent = '';
		$insidePhp = false;
		$phpCode = '';
	
		foreach ($tokens as $token) {
			if (is_array($token)) {
				$tokenType = $token[0];
				$tokenValue = $token[1];
	
				if ($tokenType == T_OPEN_TAG || $tokenType == T_OPEN_TAG_WITH_ECHO) {
					if ($insidePhp) {
						$sanitizedContent .= $this->sanitizePHP($phpCode);
						$phpCode = '';
					}
					$insidePhp = true;
					$sanitizedContent .= $tokenValue;
				} elseif ($tokenType == T_CLOSE_TAG) {
					if ($insidePhp) {
						$sanitizedContent .= $this->sanitizePHP($phpCode);
						$phpCode = '';
						$insidePhp = false;
					}
					$sanitizedContent .= $tokenValue;
				} elseif ($insidePhp) {
					$phpCode .= $tokenValue;
				} else {
					$sanitizedContent .= $tokenValue;
				}
			} else {
				if ($insidePhp) {
					$phpCode .= $token;
				} else {
					$sanitizedContent += $token;
				}
			}
		}
	
		if ($insidePhp && $phpCode) {
			$sanitizedContent .= $this->sanitizePHP($phpCode);
		}
	
		return $sanitizedContent;
	}
	
	public function render($vars,$code) {
		/*
			Renderujeme izolovane
			Predideme pripadnej kolizii nazvu premennych, vyhneme sa pristupu eval-u do global scopu
		*/
		ob_start();
		$code = $this->sanitizeMIXED($code);
		(function() use ($vars,$code) {

			global $translator;
			
			$canrewrite = function($filePath) {
				if (file_exists($filePath)) {
					$ageoffile = time() - filemtime($filePath);
					if ($ageoffile < 5) return(false);
					return(true);
				}
				
				return(true);
			};

            $recursively_noncallable_check = function($var) use (&$recursively_noncallable_check) {
                if (is_callable($var)) {
                    return false;
                }
                
                if (is_array($var)) {
                    foreach ($var as $value) {
                        if (!$recursively_noncallable_check($value)) {
                            return false;
                        }
                    }
                }
            
                if (is_object($var)) {
                    foreach ($var as $property => $value) {
                        if (!$recursively_noncallable_check($value)) {
                            return false;
                        }
                    }
                }
            
                if (is_string($var)) {
                    if (function_exists($var)) {
                        if (in_array($var,SELF::phpsandbox_disabled())) {
                            return false;
                        }                        
                    }
                }
            
                return true;
            };
            
			
			foreach ($vars as $vkey => $vvalue) {
                if ($recursively_noncallable_check($vvalue) && $recursively_noncallable_check($key)) {
                    $$vkey = $vvalue;
                }				
			}

			// Aby sme sa vyhli problemom s rovnako definovanymi funkciami pri rendrovani vygenerujeme nahodny namespace.
			$namespace = "a".md5(rand(120000,800000))."\\b".md5(rand(120000,800000))."\\c".md5(rand(120000,800000))."\\d".md5(rand(120000,800000))."\\e".md5(rand(120000,800000));
			$namespace = '<?php namespace '.$namespace.';?>';

			$code = $namespace.$code;

			// Evalu sa netreba bat. Je v tomto pripade kompletne izolovany od vsetkeho nebezpecneho.
			/*echo eval("?>".$code."<?php");*/
			/*
				Mozme renderovat aj pomocou exportu do PHP a include
				Dobre je to ked ladime chyby nakolko v evale sa vela o chybach nedozvieme...
			*/
			$userkey = "";
			if (defined("__ENC_KEY__")) {
				$userkey = __ENC_KEY__;
			}

			$file_i = 0;
			while (! $canrewrite(__ROOTDIR__."/app/runtime/generator/rendering_".md5($userkey.session_id().$userkey)."_".$file_i.".php")) {
				$file_i++;
			};

            $renderToFile = false;
            if (defined("__RENDER_TO_FILE__")) {
                $renderToFile = __RENDER_TO_FILE__;
			}

            if ($renderToFile) {
                try {
                    file_put_contents(__ROOTDIR__."/app/runtime/generator/rendering_".md5($userkey.session_id().$userkey)."_".$file_i.".php",$code);
                    chmod(__ROOTDIR__."/app/runtime/generator/rendering_".md5($userkey.session_id().$userkey)."_".$file_i.".php",0644);
                    include(__ROOTDIR__."/app/runtime/generator/rendering_".md5($userkey.session_id().$userkey)."_".$file_i.".php");
                    unlink(__ROOTDIR__."/app/runtime/generator/rendering_".md5($userkey.session_id().$userkey)."_".$file_i.".php");
                } catch (Exception $e) {
                    try {
                        // Zlyhalo zapisovanie, nevieme renderovat fyzickym nacitanim ulozeneho php skriptu tak evalujeme
                        echo eval("?>".$code."<?php");
                    } catch (Exception $e) {
                        echo "Chyba: " . $e->getMessage();
                    }			
                }
            } else {
                try {
                    echo eval("?>".$code."<?php");
                } catch (Exception $e) {
                    echo "Chyba: " . $e->getMessage();
                }	
            }
			
			
		})();
		return(ob_get_clean());
	}
	
}

class private_block {
    private $block;
    private $variables;
    private $id;
    
    function __construct($block) {
		$this->block = $block;
        $this->$variables = array();
        $this->id = "pb".md5($block).md5(rand(100000,200000).rand(100000,200000).rand(100000,200000));
    }

    public function get($name) {
        if (isSet($this->$variables[$name])) return($this->$variables[$name]); else return null;
    }

    public function set($name,$value) {
        $recursively_noncallable_check = function($var) use (&$recursively_noncallable_check) {
            if (is_callable($var)) {
                return false;
            }
            
            if (is_array($var)) {
                foreach ($var as $value) {
                    if (!$recursively_noncallable_check($value)) {
                        return false;
                    }
                }
            }
        
            if (is_object($var)) {
                foreach ($var as $property => $value) {
                    if (!$recursively_noncallable_check($value)) {
                        return false;
                    }
                }
            }
        
            if (is_string($var)) {
                if (function_exists($var)) {
                    if (in_array($var,renderer_isolator::phpsandbox_disabled())) {
                        return false;
                    }                        
                }
            }
        
            return true;
        };
        if ($recursively_noncallable_check($value) && $recursively_noncallable_check($name)) {
            $this->$variables[$name] = $value;
        }	
        return $this;
    }

    public function html($html="") {
        if ($html != "") {
            $this->block = $html;
            return($this);
        } else {
            $html = "";
            foreach ($this->$variables as $name => $value) {
                if (is_array($value)) {
                    $html .= '<?php $'.$this->id.$name.' = json_decode(base64_decode("'.base64_encode($value).'"),true);?>';
                } elseif (is_object($value)) {
                    $html .= '<?php $'.$this->id.$name.' = json_decode(base64_decode("'.base64_encode($value).'"));?>';
                } else {
                    $html .= '<?php $'.$this->id.$name.' = base64_decode("'.base64_encode($value).'");?>';
                }
            }
            $html .= "\n".str_replace("{{ var: $","{{ var: $".$this->id,$this->block);
            $html = str_replace("{{_ var: $","{{_ var: $".$this->id,$html);
            $html = str_replace("{{ foreach $","{{ foreach $".$this->id,$html);
            
            return($html);
        }        
    }

}

?>