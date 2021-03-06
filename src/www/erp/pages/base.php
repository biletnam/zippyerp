<?php

namespace ZippyERP\ERP\Pages;

use Zippy\Binding\PropertyBinding;
use Zippy\Html\Label;
use Zippy\Html\Panel;
use Zippy\Html\Link\ClickLink;
use ZippyERP\ERP\Helper;
use Zippy\WebApplication as App;
use ZippyERP\System\System;
use ZippyERP\System\User;

class Base extends \Zippy\Html\WebPage
{

    public $_errormsg;
    public $_successmsg;
    public $_warnmsg;
    public $_infomsg;

    public function __construct($params = null)
    {

        \Zippy\Html\WebPage::__construct();

  
       
       
       
        $user = System::getUser();
        if ($user->user_id == 0) {
            App::Redirect("\\ZippyERP\\System\\Pages\\Userlogin");
        }

        $this->add(new ClickLink('logout', $this, 'LogoutClick'));
        $this->add(new Label('username', $user->userlogin));
        
        $cntn= \ZippyERP\System\Notify::isNotify($user->user_id);
        $this->add(new Label('newnotcnt', "".$cntn))->setVisible($cntn>0); 

    
        $this->add(new ClickLink("pageinfo"));

        $pi = $this->getPageInfo();
        $this->add(new Label("picontent", $pi,true));
        if (strlen($pi) == 0) {
            $this->pageinfo->setVisible(false);
        }

 
        $this->add(new Label("docmenu", Helper::generateMenu(1), true));
        $this->add(new Label("repmenu", Helper::generateMenu(2), true));
        $this->add(new Label("regmenu", Helper::generateMenu(3), true));
        $this->add(new Label("refmenu", Helper::generateMenu(4), true));
        $this->add(new Label("pagemenu", Helper::generateMenu(5), true));

        
        $this->_tvars["islogined"] = $user->user_id > 0;
        $this->_tvars["isadmin"] = $user->userlogin == 'admin';
    }

    public function LogoutClick($sender)
    {
        setcookie("remember", '', 0);
        System::setUser(new \ZippyERP\System\User());
        $_SESSION['user_id'] = 0;
        $_SESSION['userlogin'] = 'Гость';

        //$page = $this->getOwnerPage();
        //  $page = get_class($page)  ;
        App::Redirect("\\ZippyERP\\System\\Pages\\UserLogin");
        ;
        ;
        //    App::$app->getresponse()->toBack();
    }

    public function getPageInfo()
    {
        $class = explode("\\", get_class($this));
        $classname = $class[count($class) - 1];
        return \ZippyERP\ERP\Helper::getMetaNotes($classname);
    }

    //вывод ошибки,  используется   в дочерних страницах
   public function setError($msg) {


        $this->_errormsg = $msg;
    }

    public function setSuccess($msg) {
        $this->_successmsg = $msg;
    }

    public function setWarn($msg) {
        $this->_warnmsg = $msg;
    }

    public function setInfo($msg) {
        $this->_infomsg = $msg;
    }

    final protected function isError()
    {
        return strlen($this->_errormsg) > 0;
    }

    protected function beforeRender()
    {
         
    }

    protected function afterRender()
    {
        if (strlen($this->_errormsg) > 0)
            App::$app->getResponse()->addJavaScript("toastr.error('{$this->_errormsg}')        ", true);
        if (strlen($this->_warnmsg) > 0)
            App::$app->getResponse()->addJavaScript("toastr.warning('{$this->_warnmsg}')        ", true);
        if (strlen($this->_successmsg) > 0)
            App::$app->getResponse()->addJavaScript("toastr.success('{$this->_successmsg}')        ", true);
        if (strlen($this->_infomsg) > 0)
            App::$app->getResponse()->addJavaScript("toastr.info('{$this->_infomsg}')        ", true);


        $this->setError('');
        $this->setSuccess('');

        $this->setInfo('');
        $this->setWarn('');
    }

}
