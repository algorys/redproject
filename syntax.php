<?php
/**
 * Redproject Syntax Plugin: Display Roadmap and other things
 *
 * @author Algorys
 */

if (!defined('DOKU_INC')) die();
require 'vendor/php-redmine-api/lib/autoload.php';


class syntax_plugin_redproject extends DokuWiki_Syntax_Plugin {

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
    	// Get Project Info
        $projId = $client->api('project')->getIdByName($data['proj']);
        $projInfo = $client->api('project')->show($projId);
        // RENDERER PROJECT INFO
        $projName = $data['proj'];        
        $projParent = $projInfo['project']['parent'];
        $nameParent = $projParent['name'];
        $projHome = $projInfo['project']['homepage'];
        $projDesc = $projInfo['project']['description'];
        //echo '<p style="background-color:#3498db;color:white;">NOM_PROJET = ' . $projName . '</p>';
        $renderer->doc .= '<div>' . $projName . ' Subproject of : <a href='.$url.'/projects/'.$nameParent.'>'.$nameParent.'</a> </div>';
        if ($projDesc == ''){
            $renderer->doc .= '<div>Description : <p>Aucune description n\'est disponible pour ce projet.</p></div>';
        } else {
            $renderer->doc .= '<div>Description : <p> ' . $projDesc . '</p></div>';
        }
        echo '<p><a href='.$projHome.'>Homepage</a>';
        echo '<p>Parent Project : '.$nameParent.'<a href='.$url.'/projects/'.$nameParent.'>GOTO</a></p>';
        // VERSIONS
        $versions = $client->api('version')->all($data['proj']);
	    echo "<br>ALL VERSIONS <br>";
	    for($i = 0; $i < count($versions['versions']); $i++) {
	        $foundVersion = $versions['versions'][$i];
	        echo "--- Version $foundVersion[name] :<br>";
	        print_r($foundVersion);
	        echo "<br>";
	        $issueTotal = $client->api('issue')->all(array(
                'project_id' => $projId,
                'status_id' => '*',
                'fixed_version_id' => $foundVersion['id'],
                'limit' => 1
                ));
	        // print_r($issue);
	        $nbIssue = $issueTotal['total_count'];
	        echo "<p>#Total Issue(s) : $nbIssue";
            $issueOpen = $client->api('issue')->all(array(
                'project_id' => $projId,
                'status_id' => 'open',
                'fixed_version_id' => $foundVersion['id'],
                'limit' => 1
                ));
            // print_r($issue);
            $nbIssue = $issueOpen['total_count'];
            echo "<br>#Issue(s) Ouvertes : $nbIssue</p>";
	    }
	    echo "<br>";
        // Get Memberships & Roles of project
        echo "<br>MEMBERS <br>";
        // Initialize Array
        $usersByRole = array();
        $members = $client->api('membership')->all($projId);
        // Found each Members
        for($m = 0; $m <count($members['memberships']); $m++) {
            $memberFound = $members['memberships'][$m];
            $currentUser = $memberFound['user'];
            for($r = 0; $r <count($memberFound['roles']); $r++) {
                $currentRole = $memberFound['roles'][$r];
                $roleId = $currentRole['id'];
                // If doesn't exist in usersByRole, create it
                if(!$usersByRole[$roleId]) {
                    $currentRole['members'] = array($currentUser);
                    $usersByRole[$roleId] = $currentRole;
                } 
                // Else Push to array
                else {
                    array_push($usersByRole[$roleId]['members'], $currentUser);
                }
            }
        }
        // Display new array usersByRole
        foreach($usersByRole as $role => $currentRole) {
            echo '<p>'.$currentRole['name'].' : ';
            foreach($currentRole['members'] as $who => $currentUser) {
                echo '<span> '. $currentUser['name'] ;
            }
        }
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
                $this->_render_project($renderer, $data);
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
