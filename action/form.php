<?php

/**
 * DokuWiki Plugin fksnewsfeed (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Červeňák <miso@fykos.cz>
 */
if(!defined('DOKU_INC')){
    die();
}

/** $INPUT 
 * @news_do add/edit/
 * @news_id no news
 * @news_strem name of stream
 * @id news with path same as doku @ID
 * @news_feed how many newsfeed need display
 * @news_view how many news is display
 */
class action_plugin_fksnewsfeed_form extends DokuWiki_Action_Plugin {

    private static $modFields;
    private static $cartesField = array('email','author');
    private $helper;
    private $delete;

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function __construct() {
        $this->helper = $this->loadHelper('fksnewsfeed');
        self::$modFields = helper_plugin_fksnewsfeed::$Fields;
    }

    /**
     * 
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('HTML_EDIT_FORMSELECTION','BEFORE',$this,'form_to_news');
        $controller->register_hook('ACTION_ACT_PREPROCESS','BEFORE',$this,'save_news');
        $controller->register_hook('ACTION_ACT_PREPROCESS','BEFORE',$this,'add_to_stream');
        $controller->register_hook('TPL_ACT_RENDER','BEFORE',$this,'stream_delete');
    }

    /**
     * 
     * @global type $TEXT
     * @global type $INPUT
     * @global type $ID
     * @param Doku_Event $event
     * @param type $param
     * @return type
     */
    public function form_to_news(Doku_Event &$event) {
        global $TEXT;
        global $INPUT;
        if($INPUT->str('target') !== 'plugin_fksnewsfeed'){
            return;
        }
        $event->preventDefault();
        $form = $event->data['form'];

        if(array_key_exists('wikitext',$_POST)){
            foreach ($this->modFields as $field) {
                $data[$field] = $INPUT->param($field);
            }
        }else{
            if($INPUT->int('news_id') != null){
                $data = $this->helper->load_news_simple($INPUT->str("news_id"));
                $TEXT = $data['text'];
            }else{
                list($data,$TEXT) = $this->create_default();
            }
        }

        $form->startFieldset('Newsfeed');
        $form->addHidden('target','plugin_fksnewsfeed');
        $form->addHidden('news_id',$INPUT->str("news_id"));
        $form->addHidden('news_do',$INPUT->str('news_do'));
        $form->addHidden('news_stream',$INPUT->str('news_stream'));

        foreach (self::$modFields as $field) {
            if($field == 'text'){
                $value = $INPUT->post->str('wikitext',$data[$field]);
                $form->addElement(html_open_tag('div',array('class' => 'clearer')));
                $form->addElement(html_close_tag('div'));
                $form->addElement(form_makeWikiText($TEXT,array()));
            }else{
                $value = $INPUT->post->str($field,$data[$field]);
                $form->addElement(form_makeTextField($field,$value,$this->getLang($field),$field,null,array('list' => 'news_list_'.$field)));
            }
        }
        foreach (self::$cartesField as $field) {
            $form->addElement(form_makeDataList('news_list_'.$field,$this->helper->all_values($field)));
        }
        $form->endFieldset();
    }

    public function save_news() {
        global $INPUT;
        global $ACT;

        if($INPUT->str("target") == "plugin_fksnewsfeed"){
            global $TEXT;
            global $ID;
            if(isset($_POST['do']['save'])){
                $data = array();
                foreach (self::$modFields as $field) {
                    if($field == 'text'){
                        $data[$field] = cleanText($INPUT->str('wikitext'));
                        unset($_POST['wikitext']);
                    }else{
                        $data[$field] = $INPUT->param($field);
                    }
                }
                if($INPUT->str('news_do') == 'add'){
                    $id = $this->helper->saveNewNews($data,$INPUT->str('news_id'),FALSE);
                    $stream_id = $this->helper->stream_to_id($INPUT->str('news_stream'));
                    $arrs = array($stream_id);
                    $this->helper->create_dependence($stream_id,$arrs);
                    foreach ($arrs as $arr) {
                        $this->helper->save_to_stream($arr,$id);
                    }
                }else{
                    $this->helper->saveNewNews($data,$INPUT->str('news_id'),true);
                }
                unset($TEXT);
                unset($_POST['wikitext']);
                $ACT = 'show';
                $ID = 'start';
            }
        }
    }

    private function create_default() {
        global $INFO;
        return array(
            array('author' => $INFO['userinfo']['name'],
                'newsdate' => dformat(),
                'email' => $INFO['userinfo']['mail'],
                'text' => $this->getLang('news_text'),
                'name' => $this->getLang('news_name'),
                'image' => ''),
            $this->getLang('news_text'));
    }

    /**
     * 
     * @global type $INPUT
     * @global string $ACT
     * @global type $TEXT
     * @global type $ID
     * @global type $INFO
     * @param Doku_Event $event
     * @param type $param
     */
    public function add_to_stream() {
        global $INPUT;

        if($INPUT->str("target") == "plugin_fksnewsfeed"){


            if($INPUT->str('news_do') == 'delete'){
                $this->delete['delete'] = true;
                global $INPUT;
                $this->delete['data']['news_stream-data'] = $INPUT->str('news_stream-data');
                if($this->delete['data']['news_stream-data']){
                    $old_data = io_readFile(metaFN('fksnewsfeed:old-streams:'.$INPUT->str('news_stream'),'.csv'));
                    $new_data = $old_data."\n".$this->delete['data']['news_stream-data'];
                    $old_stream_path = metaFN('fksnewsfeed:old-streams:'.$INPUT->str('news_stream'),'.csv');

                    io_saveFile($old_stream_path,$new_data);
                    $set_save_stream = $INPUT->str('news_stream-save');
                    if(!empty($set_save_stream)){
                        $new_stream_path = metaFN('fksnewsfeed:streams:'.$INPUT->str('news_stream'),'.csv');
                        io_saveFile($new_stream_path,$this->delete['data']['news_stream-data']);
                    }
                    $display = $INPUT->str('news_stream-data');
                }else{
                    $display = io_readFile(metaFN('fksnewsfeed:streams:'.$INPUT->str('news_stream'),'.csv'));
                }
                $this->delete['data']['news_stream'] = $INPUT->str('news_stream');
                $this->delete['data']['news_stream-data'] = $display;
            }
        }
    }

    public function stream_delete(Doku_Event &$event) {
        if(!$this->delete['delete']){
            return;
        }
        $event->preventDefault();
        global $INPUT;
        global $lang;

        echo '<h1>'.$this->getLang('permut_menu').':'.$this->delete['data']['news_stream'].'</h1>';

        echo html_open_tag('legend');
        echo $this->getLang('btn_delete_news');
        echo html_close_tag('legend');


        $form = new Doku_Form(array('id' => "save",
            'method' => 'POST','action' => null));
        $form->startFieldset(null);
        $form->addHidden('stream',$INPUT->str('news_stream'));

        $form->addHidden("target","plugin_fksnewsfeed");
        $form->addHidden('news_do','delete');
        $form->addElement('<textarea name="news_stream-data" class="wikitext">'.$this->delete['data']['news_stream-data'].'</textarea>');
        $form->addElement(form_makeButton('submit','',$lang['btn_preview'],array()));
        $form->endFieldset();
        html_form('nic',$form);


        $set_stream_data = $this->delete['data']['news_stream-data'];
        if(!empty($set_stream_data)){
            echo html_open_tag('legend');
            echo $lang['btn_save'];
            echo html_close_tag('legend');


            $form = new Doku_Form(array(
                'id' => "save",
                'method' => 'POST',
                'action' => null));
            $form->startFieldset(null);
            $form->addHidden('news_stream',$this->delete['data']['news_stream']);
            $form->addHidden('news_stream-save',true);
            $form->addHidden('news_stream-data',$this->delete['data']['news_stream-data']);
            $form->addElement($this->delete['data']['news_stream-data']);
            $form->addElement(form_makeButton('submit','',$lang['btn_save'],array()));
            $form->endFieldset();

            html_form('nic',$form);
        }

        echo html_open_tag('legend');
        echo $lang['btn_preview'];
        echo html_close_tag('legend');
        echo '<div class="FKS_newsfeed_delete_stream">';
        $i = 0;
        foreach ($this->helper->loadstream($INPUT->str('news_stream'),true) as $key => $value) {
            $id = $value['news_id'];

            $e = $this->helper->_is_even($key);
            $n = str_replace(array('@id@','@even@'),array($id,$e),$this->helper->simple_tpl);
            echo html_open_tag('div',array(
                'class' => 'FKS_newsfeed_delete_stream_news',
                'data-index' => $i,
                'data-id' => $value['news_id']));
           
            echo '<div class="FKS_newsfeed_delete_stream_news_weight"><label>Weight</label><input  name="weight" value="'.$value['weight'].'" /></div>';
            echo p_render("xhtml",p_get_instructions($n),$info);
            echo '</div>';
            $i++;
        }
        echo'</div>';
    }

}
