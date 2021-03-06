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

    function getServerFromJson($server) {
        $json_file = file_get_contents(__DIR__.'/server.json');
        $json_data = json_decode($json_file, true);
        if(isset($json_data[$server])) {
            return $json_data[$server];
        } else {
            return null;
        }
    }

    function getPercent($opIssue, $totalIssue) {
        $p = $opIssue / $totalIssue;
        $progress = $p * 100;
        return round($progress, 1);
    }

    // Do the regexp
    function handle($match, $state, $pos, Doku_Handler $handler) {
        switch($state){
            case DOKU_LEXER_SPECIAL :
            case DOKU_LEXER_ENTER :
                $data = array(
                        'state'=>$state,
                        'proj'=> '',
                    );
                preg_match("/server *= *(['\"])(.*?)\\1/", $match, $server);
                if (count($server) != 0) {
                    $server_data = $this->getServerFromJson($server[2]);
                    if( ! is_null($server_data)){
                        $data['server_url'] = $server_data['url'];
                        $data['server_token'] = $server_data['api_token'];
                    }
                }
                if (!isset($data['server_token'])) {
                    $data['server_token'] = $this->getConf('redproject.API');
                }
                if (!isset($data['server_url'])) {
                    $data['server_url'] = $this->getConf('redproject.url');
                }

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

                return $data;
            case DOKU_LEXER_UNMATCHED :
                return array('state'=>$state, 'text'=>$match);
            default:
                return array('state'=>$state, 'bytepos_end' => $pos + strlen($match));
        }
    }

    // Main render_link
    function _render_project($renderer, $data) {
        $client = new Redmine\Client($data['server_url'], $data['server_token']);
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
            if ( ! empty($projParent)) {
                $nameParent = $projParent['name'];
                $parentId = $client->api('project')->getIdByName($nameParent);
                $parent = $client->api('project')->show($parentId);
                $parentIdent = $parent['project']['identifier'];
            }
            $projHome = $proj['project']['homepage'];
            $projDesc = $proj['project']['description'];
            // RENDERER PROJECT INFO
            // Title
            $renderer->doc .= '<h2 class="title">'.$this->getLang('title').'</h2>';
            if($projHome) {
               $renderer->doc .= '<div class="title">';
               $renderer->doc .= '<a href="'.$projHome.'"><div class="circle">HOME</div></a>';
               $renderer->doc .= '<div class="title-droite">';
               $renderer->doc .= '<span class="info-title">'.$projName.'</span>';
               $renderer->doc .= '<div class="see-it">';
               $renderer->doc .= '<a href="'.$data['server_url'].'/projects/'.$projIdent.'">See it in redmine</a>';
               $renderer->doc .= '</div>';// /.see-it
               $renderer->doc .= '</div>'; // /.title-droite
               $renderer->doc .= '</div>'; // /.title
            } else {
               $renderer->doc .= '<div class="title">';
               $renderer->doc .= '<a href="'.$projHome.'" title="Add Homepage"><div class="circle">+</div></a>';
               $renderer->doc .= '<div class="title-droite">';
               $renderer->doc .= '<span class="info-title">'.$projName.'</span>';
               $renderer->doc .= '<div class="see-it">';
               $renderer->doc .= '<a href="'.$data['server_url'].'/projects/'.$projIdent.'">See it in redmine</a>';
               $renderer->doc .= '</div>';// /.see-it
               $renderer->doc .= '</div>'; // /.title-droite
               $renderer->doc .= '</div>'; // /.title

            }
            // DESCRIPTION
            if ($projDesc == ''){
                $renderer->doc .= '<div class="desc"><h4>'.$this->getLang('desctitle').'</h4> <p>'.$this->getLang('description').'</p></div>';
            } else {
                $renderer->doc .= '<div class="desc"><h4>'.$this->getLang('desctitle').'</h4> <p class="desc"> ' . $projDesc . '</p></div>';
            }
            // VERSIONS
            $versions = $client->api('version')->all($data['proj']);
            // Parsing Version
            if($versions) {
                $renderer->doc .= '<div class="version"><h3>'.$this->getLang('vertitle').'</h3>';
                $renderer->doc .= '<div class="panel-group" id="version-accordion-nb" role="tablist">';
                for($i = 0; $i < count($versions['versions']); $i++) {
                    // Begin Accordion
                    $renderer->doc .= '<div class="panel panel-primary descver">';
                    $foundVersion = $versions['versions'][$i];
                    $versionId = $foundVersion['id'];
                    $renderer->doc .= '<div class="version panel-heading">';
                    $renderer->doc .= '<h4 class="panel-title">';
                    $renderer->doc .= '<a class="version" data-toggle="collapse" data-parent="#version-accordion-nb" href="#collapse-version-nb-'.$versionId.'">';
                    $renderer->doc .= '<span class="version">Version ' . $foundVersion['name'] . '</span> ';
                    $renderer->doc .= '</a>';
                    $statusClass = (($foundVersion['status'] == 'open') ? 'statusop' : 'statuscl');
                    $renderer->doc .= '<span class="'.$statusClass.'"> ' . $foundVersion['status'] . ' </span>';
                    $renderer->doc .= '</h4>'; // /.panel-title
                    $renderer->doc .= '</div>'; // /.panel-heading
                    $renderer->doc .= '<div id="collapse-version-nb-'.$versionId.'" class="panel-collapse collapse">';
                    // PANEL BODY
                    $renderer->doc .= '<div class="panel-body">';
                    // Time Entries
                    $createdOn = DateTime::createFromFormat(DateTime::ISO8601, $foundVersion['created_on']);
                    $updatedOn = DateTime::createFromFormat(DateTime::ISO8601, $foundVersion['updated_on']);
                    $renderer->doc .= '<p><b>Description :</b> '.$foundVersion['description'].'</p>';
                    $renderer->doc .= '<p><a href="'.$data['server_url'].'/versions/'.$versionId.'">See this version in redmine</a></p>';
                    $renderer->doc .= '<p><b>'.$this->getLang('createdon').'</b>'.$createdOn->format(DateTime::RFC850).'</p>';
                    $renderer->doc .= '<p><b>'.$this->getLang('updatedon').'</b>'.$updatedOn->format(DateTime::RFC850).'</p>';
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
                    // Get percent version
                    $diffIssue = $issueTotal['total_count'] - $issueOpen['total_count']; 
                    $progress = $this->getPercent($diffIssue,$issueTotal['total_count']);
                    // renderer Progressbar
                    $renderer->doc .= '<span class="col-md-3">';
                    $renderer->doc .= '<a href="'.$data['server_url'].'/projects/'.$projIdent.'/issues">'.$issueTotal['total_count'].' issues ('.$diffIssue.' closed - '.$issueOpen['total_count'].' open)</a>';
                    $renderer->doc .= '</span>'; // /.col-md-3
                    $renderer->doc .= '<span class="col-md-6">';
                    $renderer->doc .= '<div class="progress">';
                    $renderer->doc .= '<span class="progress-bar" role="progressbar" aria-valuenow="70"
  aria-valuemin="0" aria-valuemax="100" style="width:'.$progress.'%">';
                    $renderer->doc .= '<span class="doku">'.$progress.'% Complete</span>';
                    $renderer->doc .= '</span></div>'; // ./progress
                    $renderer->doc .= '</span>'; // ./col-md-6
                    $renderer->doc .= '</div>'; // /.panel-body
                    $renderer->doc .= '</div>'; // /#collapse-version-nb-'.$versionId.' .panel-collapse 
                    $renderer->doc .= '</div>'; // /.panel .panel-default
                    $renderer->doc .= '<br>';
                }
                $renderer->doc .= '</div>'; // /.panel-group
            } else {
                $renderer->doc .= '<div class="version"><h3>'.$this->getLang('vertitle').'</h3>';
                $renderer->doc .= $nbVersion . ' versions';
                $renderer->doc .= 'div class="descver"><p>' . $this->getLang('noversion') . '</p></div>';
            }
            $renderer->doc .= '</div>';

            // DETAILS
            // Get Number of Version
            for($v = 0; $v < count($versions['versions']); $v++) {
                $nbVersion = $v + 1;
            }
            // Get number of Issues
            $issueTotal = $client->api('issue')->all(array(
              'project_id' => $projId,
              'status_id' => '*',
              'limit' => 1
              ));
            $issueOpen = $client->api('issue')->all(array(
              'project_id' => $projId,
              'status_id' => 'open',
              'limit' => 1
              ));
            // Initialize Array
            $usersByRole = array();
            $members = $client->api('membership')->all($projId);
            // Found each Members
            for($m = 0; $m < count($members['memberships']); $m++) {
               // $z++;
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
            // Renderer Details
            $renderer->doc .= '<div class="details">';
            $renderer->doc .= '<h3>'.$this->getlang('hdetail').'</h3>';
            // Stats
            $renderer->doc .= '<div class="stats">';
            if($projParent == ''){
                $renderer->doc .= '<p>'.$this->getLang('mainproj').'</p>'; 
            } else {
                $renderer->doc .= '<p>'.$this->getLang('subproject').' <a href="'.$data['server_url'].'/projects/'.$parentIdent.'">'.$nameParent.'</a></p>';
            }
            $renderer->doc .= '<p>'.$this->getLang('tversion').'<span class="label label-info">'.$nbVersion.'</span>'.$this->getLang('vversion').'</p>';
            $renderer->doc .= '<p><span class="label label-success">'. $issueTotal['total_count'].'</span>'.$this->getLang('issues').'<span class="label label-warning">'.$issueOpen['total_count'].'</span>'.$this->getLang('open').'</p>'; 
            $renderer->doc .= '<p><span class="label label-info">'.$m.'</span>'.$this->getLang('membdetail').'</p>';
            $renderer->doc .= '</div>'; // /.stats
            $renderer->doc .= '</div>'; // /.details
            // MEMBERSHIPS & ROLES
            $langMembers = $this->getLang('membres');
            $renderer->doc .= '<h3 class="member">'. $langMembers . '</h3>';
            // Display new array usersByRole
            $renderer->doc .= '<div class="member">';
            foreach($usersByRole as $role => $currentRole) {
                $renderer->doc .= '<p class="member">'.$currentRole['name'].' : ';
                // Define a total to render commas
                $total = count($currentRole['members']);
                foreach($currentRole['members'] as $who => $currentUser) {
                    $userId = $currentUser['id'];
                    $mailCurrentUser = $client->api('user')->show($userId);
                    $mailUser = $mailCurrentUser['user']['mail'];
                    $renderer->doc .= ' <a href="mailto:'.$mailUser.'?Subject=Project '.$projName.'"target="_top"><span>'. $currentUser['name'] . '</span></a>' ;
                    if ($who < $total - 1) {
                        $renderer->doc .= ',';
                    }
                }
                $renderer->doc .= '</p>';
            }
            $renderer->doc .= '</div>';
        } else {
            $renderer->doc .= '<h2 class="title">'.$this->getLang('private').'</h2>';
            $renderer->doc .= '<div class="desc" style="float: none;"><h3>'.$this->getLang('info').'</h3>'.$this->getLang('norights').' </p></div>';

        }
    }
    // Dokuwiki Renderer
    function render($mode, Doku_Renderer $renderer, $data) {	
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
