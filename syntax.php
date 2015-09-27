<?php
/**
 * Redproject Syntax Plugin: Display Roadmap and other things
 *
 * @author Algorys
 */

if (!defined('DOKU_INC')) die();
require 'vendor/php-redmine-api/lib/autoload.php';


class syntax_plugin_redproject extends DokuWiki_Syntax_Plugin {

    // Get url of redmine
    // function _getRedmineUrl($url) {
	//    return $this->getConf('redproject.url').'/issues/'.$url;
    //}
    
    function _getImgName() {
        // If empty (False) get the second part
        return $this->getConf('redproject.img') ?: 'lib/plugins/redproject/images/redmine.png' ;
    }

    public function getType() {
        return 'container';
    }
    /**
     * @return string Paragraph type
     */

    public function getPType() {
        return 'normal';
    }
    // Keep syntax inside plugin
    function getAllowedTypes() {
        return array('container', 'baseonly', 'substition','protected','disabled','formatting','paragraphs');
    }

    public function getSort() {
        return 198;
    }
 
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<redproject[^>]*/>', $mode,'plugin_redproject');
        $this->Lexer->addEntryPattern('<redproject[^>]*>(?=.*</redproject>)', $mode,'plugin_redproject');
    }
    function postConnect() {
        $this->Lexer->addExitPattern('</redproject>', 'plugin_redproject');
    }
    // Do the regexp
    function handle($match, $state, $pos, $handler) {
        switch($state){
            case DOKU_LEXER_SPECIAL :
            case DOKU_LEXER_ENTER :
                $data = array(
                        'state'=>$state,
                        'proj'=> '',
                        'info'=> ''
                    );
                // Looking for id
                preg_match("/proj *= *(['\"])(.*?)\\1/", $match, $proj);
                if( count($proj) != 0 ) {
                    $data['proj'] = $proj[2];
                } else {
                    return array(
                            'state'=>$state,
                            'error'=>true,
                            'text'=>'##ERROR &lt;redproject&gt;: project name required##'
                        );
                }
                // Looking for text link
                preg_match("/info *= *(['\"])(.*?)\\1/", $match, $info);
                if( count($info) != 0 ) {
                    $data['info'] = $info[2];
                }

                return $data;
            case DOKU_LEXER_UNMATCHED :
                return array('state'=>$state, 'text'=>$match);
            default:
                return array('state'=>$state, 'bytepos_end' => $pos + strlen($match));
        }
    }

    // Main render_link
    function _render_project($renderer, $data) {
        $apiKey = ($this->getConf('redproject.API'));
        $url = $this->getConf('redproject.url');
        $client = new Redmine\Client($url, $apiKey);
	// Get the project info
        $projId = $client->api('project')->getIdByName($data['proj']);
        $projInfo = $client->api('project')->show($projId);
	// Get versions
        $versions = $client->api('version')->all($data['proj']);
	// Get info for each version ?
        //$versionInfo = $client->api('version')->show(1);
        echo "PROJ INFO <br>";
        print_r($projInfo);
	echo "<br>ALL VERSIONS <br>";
	for($i = 0; $i < count($versions['versions']); $i++) {
	    $foundVersion = $versions['versions'][$i];
	    echo "<br>--- Version $foundVersion[name] :<br>";
	    print_r($foundVersion);
	    echo "<br>";
	    $issue = $client->api('issue')->all(array(
                'project_id' => $projId,
                'status_id' => '*',
                'fixed_version_id' => $foundVersion['id'],
                'limit' => 1
                ));
	    // print_r($issue);
	    $nbIssue = $issue['total_count'];
	    echo "<p>#Issue(s) : $nbIssue</p>";
	}
	//print_r($v);	
        //print_r($i);
	    echo "<br>";
            //if($foundStatus['id'] == $myStatusId) {
            // Get is_closed value
            //    $isClosed = $foundStatus['is_closed'];
            //}
        
	
	//print_r($versions);
        echo "<br>TEST API<br>";
        print_r($versionInfo);
        echo "<br>TEST API <br>";
        print_r($issue['total_count']);

        
        // Get Id user of the Wiki if Impersonate
        //$view = $this->getConf('redissue.view');
        //if ($view == self::RI_IMPERSONATE) {
        //    $INFO = pageinfo();
        //    $redUser = $INFO['userinfo']['uid'];
            // Attempt to collect information with this user
        //    $client->setImpersonateUser($redUser);
        //}
        $issue = $client->api('issue')->show($data['id']);
    }
    // Dokuwiki Renderer
    function render($mode, $renderer, $data) {	
        if($mode != 'xhtml') return false;

        if($data['error']) {
            $renderer->doc .= $data['text'];
            return true;
        }
        switch($data['state']) {
            case DOKU_LEXER_SPECIAL :
                //$this->_render_link($renderer, $data);
                $this->_render_project($renderer, $data);
                break;
            case DOKU_LEXER_ENTER :
                //$this->_render_link($renderer, $data);
                break;
            case DOKU_LEXER_EXIT:
                //$renderer->doc .= '</div>';
            case DOKU_LEXER_UNMATCHED :
                $renderer->doc .= $renderer->_xmlEntities($data['text']);
                break;
        }
        return true;
    }
}
