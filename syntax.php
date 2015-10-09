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

    function getPercent($opIssue, $totalIssue) {
        $p = $opIssue / $totalIssue;
        $progress = $p * 100;
        return round($progress, 1);
    }

    // Do the regexp
    function handle($match, $state, $pos, $handler) {
        switch($state){
            case DOKU_LEXER_SPECIAL :
            case DOKU_LEXER_ENTER :
                $data = array(
                        'state'=>$state,
                        'proj'=> '',
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
            $parentId = $client->api('project')->getIdByName($nameParent);
            $parent = $client->api('project')->show($parentId);
            $parentIdent = $parent['project']['identifier'];
            $projHome = $proj['project']['homepage'];
            $projDesc = $proj['project']['description'];
            // RENDERER PROJECT INFO
            // Title
            $renderer->doc .= '<h2 class="title">Projet Redmine</h2>';
            if($projHome) {
               $renderer->doc .= '<div class="title">';
               //$renderer->doc .= '<div class="circle"><a href="'.$projHome.'">HOME</a></div>';
               $renderer->doc .= '<a href="'.$projHome.'"><div class="circle">HOME</div></a>';
               $renderer->doc .= '<div class="title-droite">';
               $renderer->doc .= '<span class="info-title">'.$projName.'</span>';
               $renderer->doc .= '<div class="see-it">';
               $renderer->doc .= '<a href="'.$url.'/projects/'.$projIdent.'">See it in redmine</a>';
               $renderer->doc .= '</div>';// /.see-it
               $renderer->doc .= '</div>'; // /.title-droite
               $renderer->doc .= '</div>'; // /.title
            } else {
               //$renderer->doc .= 'NO HOME';
               $renderer->doc .= '<div class="title">';
               //$renderer->doc .= '<div class="circle"><a href="'.$url.'/projects/'.$projIdent.'/settings" title="Add Homepage">+</a></div>';
               $renderer->doc .= '<a href="'.$projHome.'" title="Add Homepage"><div class="circle">+</div></a>';
               $renderer->doc .= '<div class="title-droite">';
               $renderer->doc .= '<span class="info-title">'.$projName.'</span>';
               $renderer->doc .= '<div class="see-it">';
               $renderer->doc .= '<a href="'.$url.'/projects/'.$projIdent.'">See it in redmine</a>';
               $renderer->doc .= '</div>';// /.see-it
               $renderer->doc .= '</div>'; // /.title-droite
               $renderer->doc .= '</div>'; // /.title

            }
            // DESCRIPTION
            if ($projDesc == ''){
                $renderer->doc .= '<div class="desc"><h4>Description</h4> <p>'.$this->getLang('description').'</p></div>';
            } else {
                $renderer->doc .= '<div class="desc"><h4>Description</h4> <p class="desc"> ' . $projDesc . '</p></div>';
            }
            // VERSIONS
            $versions = $client->api('version')->all($data['proj']);
            // Parsing Version
            if($versions) {
                $renderer->doc .= '<div class="version"><h3>Versions</h3>';
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
                    $renderer->doc .= $foundVersion['description'] . '</a>';
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
                    $renderer->doc .= '<p><a href="'.$url.'/versions/'.$versionId.'">See this version in redmine</a></p>';
                    $renderer->doc .= '<p>'.$this->getLang('createdon') . $createdOn->format(DateTime::RFC850) . '</p>';
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
                    $diffIssue = $issueTotal['total_count'] - $issueOpen['total_count']; 
                    $progress = $this->getPercent($diffIssue,$issueTotal['total_count']);
                    $renderer->doc .= '<p>Progress = ' . $progress . '</p>';
                    $renderer->doc .= '<span class="col-md-3">';
                    $renderer->doc .= '<a href="' . $url . '/projects/' . $projIdent . '/issues">' . $issueTotal['total_count'] . ' issues (' . $diffIssue . ' closed - ' . $issueOpen['total_count'] . ' open)</a>';
                    $renderer->doc .= '</span>'; // /.col-md-3
                    $renderer->doc .= '<span class="col-md-6">';
                    $renderer->doc .= '<div class="progress">
  <span class="progress-bar" role="progressbar" aria-valuenow="70"
  aria-valuemin="0" aria-valuemax="100" style="width:70%">
    <span class="sr-only">70% Complete</span>
  </span>
</div>';
                    $renderer->doc .= '</span>';// ./col-md-6
                        
                    $renderer->doc .= '</div>'; // /.panel-body
                    $renderer->doc .= '</div>'; // /#collapse-version-nb-'.$versionId.' .panel-collapse 
                    $renderer->doc .= '</div>'; // /.panel .panel-default
                    $renderer->doc .= '<br>';
                }
                $renderer->doc .= '</div>'; // /.panel-group
            } else {
                $renderer->doc .= '<div class="version"><h3>Versions</h3>';
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
            for($m = 0; $m <count($members['memberships']); $m++) {
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
            $renderer->doc .= '<div class="details">';
            $renderer->doc .= '<h3>Détails du Projet</h3>';
            // Stats
            $renderer->doc .= '<div class="stats">';
            if($projParent == ''){
                $renderer->doc .= '<p>'.$this->getLang('mainproj').'</p>'; 
            } else {
                $renderer->doc .= '<p>'.$this->getLang('subproject').' <a href="'.$url.'/projects/'.$parentIdent.'">'.$nameParent.'</a></p>';
            }
            $renderer->doc .= '<p>Il y a actuellement '.$nbVersion.' versions.';
            $renderer->doc .= '<p>'. $issueTotal['total_count'].' issues dont '. $issueOpen['total_count'].' ouvertes</p>'; 
            $renderer->doc .= '<p>'.$m.' membres participent au projet.</p>';
            $renderer->doc .= '</div>'; // /.stats
            $renderer->doc .= '</div>'; // /.details
            // MEMBERSHIPS & ROLES
            $langMembers = $this->getLang('membres');
            $renderer->doc .= '<h3 class="member">'. $langMembers . '</h3>';
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
