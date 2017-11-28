<?php
class Action
{
	protected $smarty;
	protected $postStr;
	protected $postObj;
	protected $msgtype;
	protected $content;
	protected $fromUsername;
	protected $toUsername;
	public function responseMessage()
	{
		
	}

	public function assign($str,$param)
	{
		if (!isset ($this->smarty)) {
            $this->smarty = new Smarty;
            $this->smarty->template_dir = "./".__APPNAME__."/Tpl/";
            $this->smarty->left_delimiter="{{";
			$this->smarty->right_delimiter="}}";
        }

        $this->smarty->assign($str,$param);
	}

	public function Display($tplname='')
	{
		if (!isset ($this->smarty)) {
            $this->smarty = new Smarty;
            $this->smarty->template_dir = "./".__APPNAME__."/Tpl/";
            $this->smarty->left_delimiter="{{";
			$this->smarty->right_delimiter="}}";            
        }
        $this->smarty->display('index.tpl');
	}	

} 
