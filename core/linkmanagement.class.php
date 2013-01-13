<?php
## Link Tracking System
## Copyleft - Damian Kęska
##
## This program is free software: you can redistribute it and/or modify
## it under the terms of the GNU General Public License as published by
## the Free Software Foundation, either version 3 of the License, or
## (at your option) any later version.
## 
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
## 
## You should have received a copy of the GNU General Public License
## along with this program.  If not, see <http://www.gnu.org/licenses/>.

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function stripLink($string) {

    return strtolower(trim(preg_replace('~[^0-9a-z]+~i', '-', html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8')), '-'));
}

function sqlite_escape_string ($string) { return SQLite3::escapeString($string); }

class LinkValidation {
    public function validate($link) {

        if (filter_var($link, FILTER_VALIDATE_URL) == False)
            return "Not an URL";

        if (strlen($link) > 1024)
            return "URL adress is too long, max length is 1024 bytes.";
            
        if ($this->validateResponse($link) == False)
            return "Cannot retrieve data from remote url, maybe the server is down?";

        return "Fine.";
    }

    private function validateResponse($link) {
        $opts = array('http' =>array('method'  => 'GET','timeout' => 3));
        $context  = stream_context_create($opts);
        $result = @file_get_contents($link, false, $context, -1, 40000);

        if ($result == "" or $result == False)
            return False;

        return True;
    }

}


class LinkManagement {
    private $_dbFile = '';
    private $newDB = False;
    public $useSession = False; // use session as a little cache
    public $saveEveryVisit = False;  // save every visit with full user-agent, ip, date and referer

    public function __construct ($dbFile) {
        $this->_dbFile = $dbFile;

        # check if database already exists
        if(!is_file($dbFile))
            $this->newDB = True;

        $this->SQL = new SQLite3($dbFile);

        # create new database
        if($this->newDB == True)
            $this->createDatabase();

        $this->SQL->busyTimeout(500);
    }


    # Caching in session
    private function sessionSet($id, $cache) {
        /** 

            Create visit cache of given id (set as visited to minimalize SQL usage)

        **/

        $_SESSION['_linkts']['id_'.$id] = $cache;
        return True;
    }

    public function sessionCheck($id) {
        /** 

            Checking if user already visited this link

        **/

        if(isset($_SESSION['_linkts']['id_'.$id]))
            return $_SESSION['_linkts']['id_'.$id];

        return False;    
    }

    public function createDatabase() {
        /** 

            If database doesnt exists create tables for first time

        **/

        $this->SQL->query("CREATE TABLE `links` (id VARCHAR(60) PRIMARY KEY ASC, link VARCHAR(1024), useragent VARCHAR(1024), ip VARCHAR(16), date INTEGER(68), uniq VARCHAR(128), uid INTEGER(10));");
        $this->SQL->query("CREATE TABLE `visits` (id INTEGER PRIMARY KEY ASC, linkid VARCHAR(60), useragent VARCHAR(1024), ip VARCHAR(16), date INTEGER(68), referer VARCHAR(1024), count INTEGER(20), uid INTEGER(10));");
    }

    public function add($link, $useragent='', $ip='', $date='now', $uid, $linkid='') {
        /** 

            Add new link to database

        **/

        if ($linkid == '')
        {
            $linkid = generateRandomString();
        } else {
            $linkid = stripLink($linkid);        
        }


        $SQL = $this->SQL->query("SELECT `uniq` FROM `links` WHERE `id`='".sqlite_escape_string($linkid)."';");

        while (count($SQL->fetchArray()) > 0)
        {
            $linkid = generateRandomString();
            $SQL = $this->SQL->query("SELECT `uniq` FROM `links` WHERE `id`='".sqlite_escape_string($linkid)."';");
        }

        if($date == 'now')
            $date = time();

        $uniq = md5($date.$link); // unique id to use select on

        $SQL = $this->SQL->query("INSERT INTO `links` (id, link, useragent, ip, date, uniq, uid) VALUES ('".sqlite_escape_string($linkid)."', '".sqlite_escape_string($link)."', '".sqlite_escape_string($useragent)."', '".sqlite_escape_string($ip)."', '".intval($date)."', '".$uniq."', '".sqlite_escape_string($uid)."');");

        $SQL = $this->SQL->query("SELECT `id` FROM `links` WHERE `uniq`='".$uniq."';");
        $Array = $SQL->fetchArray();

        return $Array['id'];
    }

    public function getLinksByUid($uid) {
        $SQL = $this->SQL->query("SELECT * FROM `links` WHERE `uid`='".sqlite_escape_string($uid)."';");
        $Resultset = array();

        while ($Results = $SQL->fetchArray())
        {
            $Resultset[] = $Results;
        }    

        return $Resultset;
    }

    public function getLinksByLink($uid, $link) {
        $SQL = $this->SQL->query("SELECT * FROM `links` WHERE `link`='".sqlite_escape_string($link)."' AND `uid`='".sqlite_escape_string($uid)."';");
        return $SQL->fetchArray();    
    }

    public function getVisitsById($uid, $id) {
        $SQL = $this->SQL->query("SELECT * FROM `visits` WHERE `linkid`='".sqlite_escape_string($id)."' AND `uid`='".sqlite_escape_string($uid)."';");
        $Resultset = array();

        while ($Results = $SQL->fetchArray())
        {
            $Resultset[] = $Results;
        }    

        return $Resultset;
    }

    public function getVisitsByLink($uid, $link) {
        $SQL = $this->SQL->query("SELECT * FROM `visits` WHERE `link`='".sqlite_escape_string($link)."' AND `uid`='".sqlite_escape_string($uid)."';");
        $Resultset = array();

        while ($Results = $SQL->fetchArray())
        {
            $Resultset[] = $Results;
        }    

        return $Resultset;
    }

    public function getUserLink($id, $uid) {
        /** 

            Get full `link` adress from database (searching by `id`)

        **/

        $SQL = $this->SQL->query("SELECT * FROM `links` WHERE `id`='".sqlite_escape_string($id)."' AND `uid`='".sqlite_escape_string($uid)."';");
        return $SQL->fetchArray();
    }


    public function getLink($id) {
        /** 

            Get full `link` adress from database (searching by `id`)

        **/

        $SQL = $this->SQL->query("SELECT * FROM `links` WHERE `id`='".sqlite_escape_string($id)."';");

        $Array = $SQL->fetchArray();

        if(count($Array) > 0)
        {
            return array($Array['uid'], $Array['link']);
        }

        return False;
    }

    public function exists($link) {
        /** 

            Check if given url exists in database

        **/

        $SQL = $this->SQL->query("SELECT `id` FROM `links` WHERE `link`='".sqlite_escape_string($link)."';");
        $Array = $SQL->fetchArray();

        if($Array == False)
            return False;

        if(count($Array) > 0)
            return True;

        return False; 
    }

    public function checkVisit($id, $ip) {
        /** 

            Check if user already visited this link before 

        **/

        $SQL = $this->SQL->query("SELECT `date` FROM `visits` WHERE `linkid`='".sqlite_escape_string($id)."' AND `ip`='".sqlite_escape_string($ip)."';");

        $Array = $SQL->fetchArray();

        if ($Array == False)
            return False;

        if (count($Array) > 0)
            return True;    

        return False;
    }

    public function visit($id, $useragent, $ip, $date='now', $referer, $link, $uid) {
        /** 

            Save user's visits and count it

        **/

        if ($this->useSession == True)
        {
            if($this->sessionCheck($id) == True)
            {
                $this->updateVisits($id, $ip);
                return False;
            } else
                $this->sessionSet($id, array($link, $uid));
        }

        if($date == 'now')
            $date = time();

        if($this->checkVisit($id, $ip) == False or $this->saveEveryVisit == True)
            $this->SQL->query("INSERT INTO `visits` (id, linkid, useragent, ip, date, referer, count, uid) VALUES (null, '".sqlite_escape_string($id)."', '".sqlite_escape_string($useragent)."', '".sqlite_escape_string($ip)."', '".intval($date)."', '".sqlite_escape_string($referer)."', 0, '".sqlite_escape_string($uid)."');");
        else {
            $this->updateVisits($id, $ip);
        }

        return True;
    }

    private function updateVisits($id, $ip) {
        $this->SQL->query("UPDATE `visits` SET count=count+1 WHERE `linkid`='".sqlite_escape_string($id)."' AND `ip`='".sqlite_escape_string($ip)."';");

        return True;    
    }

}

#$a = new LinkManagement('/var/www/localhost/htdocs/app/linkts/data/database/test.sqlite');
#$a -> add("http://google.com", "Linux Chromium etc.", "1.2.3.4", "now");
#$a -> visit(1, "Linux Firefox", "1.2.3.4", "now", "http://yahoo.com");
?>
