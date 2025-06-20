<?php
// Trieda response len robi operacie nad REQUESTOM. Pouzivame ju hlavne preto, aby middleware vedel ci vrati response ci nie.
namespace Dotsystems\App\Parts;
use Dotsystems\App\DotApp;

class Response {
    public $dotapp;
    public $dotApp;
    public $DotApp;

    public $response;

    function __construct($responseCode, $responseBody = "") {
        $this->dotapp = DotApp::dotApp();
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
        $this->response = &$this->dotApp->request->response;
    
        if (is_array($responseBody)) {
            $this->response->status = $responseCode;
    
            foreach ($responseBody as $key => $value) {
                if (property_exists($this->response, $key)) {
                    $this->response->$key = $value;
                } else {
                    $this->response->data[$key] = $value;
                }
            }
        } else {
            $this->response->body = $responseBody;
            $this->response->status = $responseCode;
        }
    }
    

    public function __get($name) {
		return $this->response->$name ?? null;
	}

	public function __set($name, $value) {
		$this->response->$name = $value;
	}

	public function __isset($name) {
		return isset($this->response->$name);
	}
	
}



?>