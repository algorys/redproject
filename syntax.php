<?php
/**
 * Redproject Syntax Plugin: Display Roadmap and other things
 *
 * @author Algorys
 */

if (!defined('DOKU_INC')) die();
require 'vendor/php-redmine-api/lib/autoload.php';


class syntax_plugin_redproject extends DokuWiki_Syntax_Plugin {
    const RI_IMPERSONATE = 4;

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
        // Get Id user of the Wiki if Impersonate
        $view = $this->getConf('redproject.view');
        if ($view == self::RI_IMPERSONATE) {
            $redUser = $_SERVER['REMOTE_USER'];
            // Attempt to collect information with this user
            $client->setImpersonateUser($redUser);
        }
    	// Get Project Info
        $proj = $client->api('project')->show($data['proj']);
        if($proj){
            $projId = $proj['project']['id'];
            $projIdent = $proj['project']['identifier'];
            $projName = $proj['project']['name'];        
            $projParent = $proj['project']['parent'];
            $nameParent = $projParent['name'];
            $projHome = $proj['project']['homepage'];
            $projDesc = $proj['project']['description'];
            // RENDERER PROJECT INFO
            // Title
             if($projHome == '') {
                $renderer->doc .= '<div class="title"><img class="title" src="lib/plugins/redproject/images/home.png">' . $projName . '</div>';
             } else {
                $renderer->doc .= '<div class="title"><a href='.$projHome.'><img class="title" src="lib/plugins/redproject/images/home.png"></a><p>' . $projName . '</p></div>';
            }
            // Parent
            if($projParent == ''){
                $renderer->doc .= '<div class="parent">'.$this->getLang('mainproj').'<br></div>';
            } else {
                $projIdParent = $client->api('project')->getIdByName($nameParent);
                $projInfoParent = $client->api('project')->show($projIdParent);
                $projIdentParent = $projInfoParent['project']['identifier'];
                $renderer->doc .= '<div class="parent">' . $this->getLang('subproject') . ' <a href='.$url.'/projects/'.$projIdentParent.'>'.$nameParent.'</a> </div>';
            }
            // Description
            if ($projDesc == ''){
                $renderer->doc .= '<div class="desc"><h3>Description</h3> <p>'.$this->getLang('description').'</p></div>';
            } else {
                $renderer->doc .= '<div class="desc"><h3>Description</h3> <p class="desc"> ' . $projDesc . '</p></div>';
            }
            // VERSIONS
            $versions = $client->api('version')->all($data['proj']);
            $renderer->doc .= '<h3>Versions</h3>';
            // Parsing Version
            for($i = 0; $i < count($versions['versions']); $i++) {
                $foundVersion = $versions['versions'][$i];
                $versionId = $foundVersion['id'];
                $renderer->doc .=  '<p class="version"><span class="version">Version ' . $foundVersion['name'] . '</span> ';
                $renderer->doc .=  ' - <a class="version" href="'.$url.'/versions/'.$versionId.'">' . $foundVersion['description'] . '</a>';
                // Status of Versions
                if($foundVersion['status'] == 'open') {
                    $renderer->doc .= '<span class="statusop"> "' . $foundVersion['status'] . '"</span></p>';
                } else {
                    $renderer->doc .= '<span class="statuscl"> "' . $foundVersion['status'] . '"</span></p>';
                }
                // Time Entries
                $createdOn = DateTime::createFromFormat(DateTime::ISO8601, $foundVersion['created_on']);
                $updatedOn = DateTime::createFromFormat(DateTime::ISO8601, $foundVersion['updated_on']);
                $renderer->doc .= '<div class="descver"><p>'.$this->getLang('createdon') . $createdOn->format(DateTime::RFC850) . '</p>';
                $renderer->doc .= '<p>'.$this->getLang('updatedon') . $updatedOn->format(DateTime::RFC850) . '</p>';
                // Issues of Versions
                $issueTotal = $client->api('issue')->all(array(
                    'project_id' => $projId,
                    'status_id' => '*',
                    'fixed_version_id' => $foundVersion['id'],
                    'limit' => 1
                    ));
                // Total issues & open 
                $issueOpen = $client->api('issue')->all(array(
                    'project_id' => $projId,
                    'status_id' => 'open',
                    'fixed_version_id' => $foundVersion['id'],
                    'limit' => 1
                    ));
                //$nbIssue = $issueOpen['total_count'];
                $diffIssue = $issueTotal['total_count'] - $issueOpen['total_count']; 
                $renderer->doc .= '<a href="' . $url . '/projects/' . $projIdent . '/issues">' . $issueTotal['total_count'] . ' issues (' . $diffIssue . ' closed - ' . $issueOpen['total_count'] . ' open)</a></div>';
                $renderer->doc .= '<br>';
            }
            // MEMBERSHIPS & ROLES
            $langMembers = $this->getLang('membres');
            $renderer->doc .= '<h3 class="member">'. $langMembers . '</h3>';
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
            $renderer->doc .= '<div class="member">';
            foreach($usersByRole as $role => $currentRole) {
                $renderer->doc .= '<p class="member">'.$currentRole['name'].' : ';
                foreach($currentRole['members'] as $who => $currentUser) {
                    $userId = $currentUser['id'];
                    $mailCurrentUser = $client->api('user')->show($userId);
                    $mailUser = $mailCurrentUser['user']['mail'];
                    $renderer->doc .= '<a href="mailto:'.$mailUser.'?Subject=Project '.$projName.'"target="_top"><span> '. $currentUser['name'] . '</span></a>' ;
                }
                $renderer->doc .= '</p>';
            }
            $renderer->doc .= '</div>';
        } else {
            $renderer->doc .= '<div class="title"><img class="title" src="lib/plugins/redproject/images/home.png">Projet Privé</div><br>';
            $renderer->doc .= '<div class="desc"><h3>Information</h3>'.$this->getLang('norights').' </p></div>';

        }
    }
    // Dokuwiki Renderer
    function render($mode, $renderer, $data) {	
        $renderer->info['cache'] = false;
        if($mode != 'xhtml') return false;

        if($data['error']) {
            $renderer->doc .= $data['text'];
            return true;
        }
        switch($data['state']) {
            case DOKU_LEXER_SPECIAL :
                $this->_render_project($renderer, $data);
                break;
            case DOKU_LEXER_ENTER :
                $this->_render_project($renderer, $data);
                break;
            case DOKU_LEXER_EXIT:
            case DOKU_LEXER_UNMATCHED :
                $renderer->doc .= $renderer->_xmlEntities($data['text']);
                break;
        }
        return true;
    }
}
