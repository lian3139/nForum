<?php
/**
 * search controller for nforum
 *
 * @author xw
 */
load('model/board');
class SearchController extends NF_Controller {

    public function indexAction(){
        $this->js[] = "forum.search.js";
        $this->css[] = "search.css";
        $this->notice[] = array("url"=>"", "text"=>"��������");
        $this->set("site", c('search.site'));

        //for default search day
        $this->set("searchDay", c("search.day"));
    }

    public function articleAction(){
        $this->js[] = "forum.board.js";
        $this->css[] = "board.css";
        $this->notice[] = array("url"=>"", "text"=>"�������");

        $day = $title1 = $title2 = $title3 = $author = $t = "";

        if(isset($this->params['url']['t1']))
            $title1 = trim(rawurldecode($this->params['url']['t1']));
        if(isset($this->params['url']['t2']))
            $title2 = trim(rawurldecode($this->params['url']['t2']));
        if(isset($this->params['url']['tn']))
            $title3 = trim(rawurldecode($this->params['url']['tn']));
        if(isset($this->params['url']['au']))
            $author = trim($this->params['url']['au']);
        if(isset($this->params['url']['d']))
            $day = intval($this->params['url']['d']);
        $title1 = nforum_iconv('utf-8', $this->encoding, $title1);
        $title2 = nforum_iconv('utf-8', $this->encoding, $title2);
        $title3 = nforum_iconv('utf-8', $this->encoding, $title3);
        $m = isset($this->params['url']['m']);
        $a = isset($this->params['url']['a']);
        $full = isset($this->params['url']['f']);
        $site = c('search.site');
        $return =  c("search.max");

        $res = array();
        $u = User::getInstance();
        if($title1 == '' && $title3 == '' && $author == '' && !$m && !$a){
            $res = array();
        }else if($full && $site && $u->isAdmin()){
            load(array('model/section', 'model/threads'));
            $secs = array_keys(c("section"));
            foreach($secs as $v){
                $sec = Section::getInstance($v, Section::$ALL);
                foreach($sec->getList() as $brd){
                    if(!$brd->isNormal())
                        continue;
                    $res = array_merge($res, Threads::search($brd, $title1, $title2, $title3, $author, $day, $m, $a, $return));
                }
            }
        }else{
            $b = @$this->params['url']['b'];
            try{
                $brd = Board::getInstance($b);
            }catch(BoardNullException $e){
                $this->error(ECode::$BOARD_NONE);
            }
            load('model/threads');
            $res = Threads::search($brd, $title1, $title2, $title3, $author, $day, $m, $a, $return);
        }

        $p = 1;
        if(isset($this->params['url']['p']))
            $p = $this->params['url']['p'];

        load("inc/pagination");
        $page = new Pagination(new ArrayPageableAdapter($res), c("pagination.search"));

        $threads = $page->getPage($p);
        $info = false;
        $curTime = strtotime(date("Y-m-d", time()));
        $pageArticle = c("pagination.article");
        foreach($threads as $v){
            $tabs = ceil($v->articleNum / $pageArticle);
            $last = $v->LAST;
            $postTime = ($curTime > $v->POSTTIME)?date("Y-m-d", $v->POSTTIME):(date("H:i:s", $v->POSTTIME)."&emsp;");
            $replyTime = ($curTime > $last->POSTTIME)?date("Y-m-d", $last->POSTTIME):(date("H:i:s", $last->POSTTIME)."&emsp;");
            $info[] = array(
                "title" => nforum_html($v->TITLE),
                "poster" => $v->isSubject()?$v->OWNER:"ԭ����ɾ��",
                "postTime" => $postTime,
                "gid" => $v->ID,
                "last" => $last->OWNER,
                "replyTime" => $replyTime,
                "page" => $tabs,
                "bName" => $v->getBoard()->NAME,
                "num" => $v->articleNum - 1
            );
        }
        $this->set("info", $info);
        $query = $this->params['url'];
        unset($query['url']);
        unset($query['p']);
        unset($query['ext']);
        foreach($query as $k=>&$v)
            $v = $k . '=' . rawurlencode($v);
        $query[] = "p=%page%";
        $link = "{$this->base}/s/article?". join("&", $query);
        $this->set("pageBar", $page->getPageBar($p, $link));
        $this->set("pagination", $page);
    }

    public function boardAction(){
        $this->css[] = "board.css";
        $this->js[] = "forum.board.js";
        $this->notice[] = array("url"=>"", "text"=>"�������");

        $b = isset($this->params['url']['b'])?$this->params['url']['b']:"";
        $ret = false;
        $b = trim(rawurldecode($b));
        $b = nforum_iconv('utf-8', $this->encoding, $b);
        $boards = Board::search($b);
        if(count($boards) == 1)
            $this->redirect("/board/". $boards[0]->NAME);
        foreach($boards as $brd){
            $threads = $brd->getTypeArticles(0, 1, Board::$ORIGIN);
            if(!empty($threads)){
                $threads = $threads[0];
                $last = array(
                    "id" => $threads->ID,
                    "title" => nforum_html($threads->TITLE),
                    "owner" => $threads->isSubject()?$threads->OWNER:"ԭ����ɾ��",
                    "date" => date("Y-m-d H:i:s", $threads->POSTTIME)
                );
            }else{
                $last["id"] = "";
                $last["title"] = $last["owner"] = $last["date"] = "��";
            }
            $bms = split(" ", $brd->BM);
            foreach($bms as &$bm){
                if(preg_match("/[^0-9a-zA-Z]/", $bm)){
                    $bm = array($bm, false);
                }else{
                    $bm = array($bm, true);
                }
            }
            $ret[] = array(
                "name" => $brd->NAME,
                "desc" => $brd->DESC,
                "type" => $brd->isDir()?"section":"board",
                "bms" => $bms,
                "curNum" => $brd->CURRENTUSERS,
                "todayNum" => $brd->getTodayNum(),
                "threadsNum" => $brd->getThreadsNum(),
                "articleNum" => $brd->ARTCNT,
                "last" => $last
            );
        }
        $this->set("sec", $ret);
        $this->set("noBrd", ECode::msg(ECode::$SEC_NOBOARD));
        $this->render("index", array("/section/"));
    }
}
