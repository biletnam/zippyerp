<?php

namespace ZippyERP\ERP\Pages\Doc;

use Zippy\Html\DataList\DataView;
 
use Zippy\Html\Form\Button;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;
use ZippyERP\ERP\Entity\Doc\Document;
use ZippyERP\ERP\Entity\Item;
use ZippyERP\ERP\Entity\Stock;
use ZippyERP\ERP\Entity\Store;
use ZippyERP\ERP\Helper as H;
use Zippy\WebApplication as App;

/**
 * Страница  документа инвентаризация
 */
class Inventory extends \ZippyERP\ERP\Pages\Base
{

    public $_itemlist = array();
    private $_doc;
    private $_rowid = 0;

    public function __construct($docid = 0)
    {
        parent::__construct();

        $this->add(new Form('docform'));
        $this->docform->add(new TextInput('document_number'));
        $this->docform->add(new Date('document_date'))->setDate(time());
        $this->docform->add(new DropDownChoice('itemtype', array(201 => 'Материал', 25 => 'Полуфабрикат', 281 => 'Товар'), 201))->onChange($this, "OnItemType");
        $this->docform->add(new DropDownChoice('store', Store::findArray("storename", "store_type = " . Store::STORE_TYPE_OPT)))->onChange($this, 'OnChangeStore');

        $this->docform->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->docform->add(new SubmitLink('load'))->onClick($this, 'loadOnClick');
        $this->docform->add(new SubmitButton('savedoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new SubmitButton('execdoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new Button('backtolist'))->onClick($this, 'backtolistOnClick');

        $this->add(new Form('editdetail'))->setVisible(false);
        $this->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editrealquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editprice'));
        $this->editdetail->add(new DropDownChoice('edititem'));
        $this->editdetail->edititem->onChange($this, 'OnChangeItem',true);


        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new SubmitButton('submitrow'))->onClick($this, 'saverowOnClick');

        if ($docid > 0) {    //загружаем   содержимок  документа на страницу
            $this->_doc = Document::load($docid);
            $this->docform->document_number->setText($this->_doc->document_number);

            $this->docform->document_date->setDate($this->_doc->document_date);


            $this->docform->store->setValue($this->_doc->headerdata['store']);
            $this->docform->itemtype->setValue($this->_doc->headerdata['itemtype']);


            foreach ($this->_doc->detaildata as $item) {
                $item = new Item($item);
                $this->_itemlist[$item->item_id] = $item;
            }
        } else {
            $this->_doc = Document::create('Inventory');
            $this->docform->document_number->setText($this->_doc->nextNumber());
        }

        $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_itemlist')), $this, 'detailOnRow'));
        $this->docform->add(new \Zippy\Html\DataList\Paginator('pag', $this->docform->detail));
        $this->docform->detail->Reload();
        $this->OnItemType($this->docform->itemtype);
    }

    public function detailOnRow($row)
    {
        $item = $row->getDataItem();

        $row->add(new Label('item', $item->itemname));
        $row->add(new Label('measure', $item->measure_name));
        $row->add(new Label('quantity',"". $item->quantity / 1000));
        $row->add(new Label('realquantity',"". $item->realquantity / 1000));
        $row->add(new Label('price', H::fm($item->price)));
        $row->add(new Label('amount', H::fm(($item->quantity / 1000) * $item->price)));
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
    }

    public function deleteOnClick($sender)
    {
        $item = $sender->owner->getDataItem();
        // unset($this->_itemlist[$item->item_id]);

        $this->_itemlist = array_diff_key($this->_itemlist, array($item->stock_id => $this->_itemlist[$item->stock_id]));
        $this->docform->detail->Reload();
    }

    public function addrowOnClick($sender)
    {

        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);
        $this->_rowid = 0;
        //очищаем  форму
        $this->editdetail->edititem->setValue(0);
        
        $this->editdetail->editquantity->setText("1");
        $this->editdetail->editrealquantity->setText("1");

        $this->editdetail->editprice->setText("");
    }

    public function editOnClick($sender)
    {

        $stock = $sender->getOwner()->getDataItem();

        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);

        $this->editdetail->editquantity->setText($stock->quantity / 1000);
        $this->editdetail->editrealquantity->setText($stock->realquantity / 1000);
        $this->editdetail->editprice->setText(H::fm($stock->price));


        //  $list = Stock::findArrayEx("closed  <> 1   and store_id={$stock->store_id}");
        $this->editdetail->edititem->setValue($stock->stock_id);
        


        $this->_rowid = $stock->stock_id;
    }

    public function saverowOnClick($sender)
    {
        $id = $this->editdetail->edititem->getValue();
        if ($id == 0) {
            $this->setError("Не выбран ТМЦ");
            return;
        }


        $stock = Stock::load($id);


        $stock->quantity = 1000 * $this->editdetail->editquantity->getText();
        $stock->realquantity = 1000 * $this->editdetail->editrealquantity->getText();
        if ($stock->quantity == $stock->realquantity) {
            $this->setError("Одинаковое количество");
            return;
        }

        // $stock->partion = $stock->price;
        $stock->price = $this->editdetail->editprice->getText() * 100;


        unset($this->_itemlist[$this->_rowid]);
        $this->_itemlist[$stock->stock_id] = $stock;
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();
    }

    public function cancelrowOnClick($sender)
    {
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
    }

    public function savedocOnClick($sender)
    {
        if ($this->checkForm() == false) {
            return;
        }

        $this->calcTotal();

        $this->_doc->headerdata = array(
            'store' => $this->docform->store->getValue(),
            'itemtype' => $this->docform->itemtype->getValue(),
            'storename' => $this->docform->store->getValueName(),
            'itemtypename' => $this->docform->itemtype->getValueName()
        );
        $this->_doc->detaildata = array();
        foreach ($this->_itemlist as $item) {
            $this->_doc->detaildata[] = $item->getData();
        }


        $this->_doc->document_number = $this->docform->document_number->getText();
        $this->_doc->document_date = $this->docform->document_date->getDate();
        $isEdited = $this->_doc->document_id > 0;


        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            $this->_doc->save();
            if ($sender->id == 'execdoc') {
                $this->_doc->updateStatus(Document::STATE_EXECUTED);
            } else {
                $this->_doc->updateStatus($isEdited ? Document::STATE_EDITED : Document::STATE_NEW);
            }

            $conn->CommitTrans();
            App::RedirectBack();
        } catch (\ZippyERP\System\Exception $ee) {
            $conn->RollbackTrans();
            $this->setError($ee->getMessage());
        } catch (\Exception $ee) {
            $conn->RollbackTrans();
            throw new \Exception($ee->getMessage());
        }
    }

    /**
     * Расчет  итого
     *
     */
    private function calcTotal()
    {
        
    }

    public function loadOnClick($sender)
    {
        $this->_itemlist = array();
        $conn = \ZDB\DB::getConnect();

        $store_id = $this->docform->store->getValue();
        $account_id = $this->docform->itemtype->getValue();

        $qt = " select coalesce(sum(quantity),0)    from erp_account_subconto  where   erp_account_subconto.stock_id = erp_stock_view.stock_id  and date(document_date) <= " . $conn->DBDate($this->docform->document_date->getDate());
        $sql = "select erp_stock_view.*,({$qt}) as quantity from erp_stock_view where store_id={$store_id} and closed <> 1  and stock_id in(select stock_id  from  erp_account_subconto  where  account_id= {$account_id}) order by  itemname";
        $rows = Stock::findBySql($sql);
        foreach ($rows as $stock) {

            $stock->realquantity = $stock->quantity;
            $this->_itemlist[$stock->stock_id] = $stock;
        }


        $this->docform->detail->Reload();
    }

    public function OnItemType($sender)
    {
        $this->_itemlist = array();
        $this->docform->detail->Reload(); 
        
        $store_id = $this->docform->store->getValue();
        $account_id = $this->docform->itemtype->getValue();
        
        $this->editdetail->edititem->setOptionList(Stock::findArrayEx("store_id={$store_id} and closed <> 1  and stock_id in(select stock_id  from  erp_account_subconto  where  account_id= {$account_id}) "));
    }

    /**
     * Валидация   формы
     *
     */
    private function checkForm()
    {

        if (count($this->_itemlist) == 0) {
            $this->setError("Не введен ни один  ТМЦ");
        }

        return !$this->isError();
    }

    public function beforeRender()
    {
        parent::beforeRender();

        $this->calcTotal();
    }

    public function backtolistOnClick($sender)
    {
        App::RedirectBack();
    }

    public function OnChangeStore($sender)
    {
        //очистка  списка  товаров
        $this->_itemlist = array();
        $this->docform->detail->Reload();
    }
   

    public function OnChangeItem($sender)
    {

        $id = $sender->getValue();
        $stock = Stock::load($id);
        //   $item = Item::load($stock->item_id);
        $this->editdetail->editprice->setText(H::fm($stock->price));


        $this->updateAjax(array('editprice'));
    }

}
