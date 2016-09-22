<?php
/**
 * Publish private instagram posts onto a micropub endpoint.
 *
 * Works with any instagram profile that you are subscribed to.
 * Screen-scrapes the instagram web interface.
 *
 * Worked at 2016-09-11.
 *
 * @author  Christian Weiske <cweiske@cweiske.de>
 * @license AGPLv3 or later
 */
require_once __DIR__ . '/config.php';
if (!is_dir($dldir)) {
    mkdir($dldir);
}

//allow utf-8 characters with escapeshellarg()
setlocale(LC_CTYPE, "en_US.UTF-8");

function fetchJson($url)
{
    global $curl;
    $json = shell_exec($curl . ' ' . escapeshellarg($url));
    $data = json_decode($json);
    if ($data === null) {
        echo "invalid json\n";
        echo $json . "\n";
        exit(1);
    }
    return $data;
}

function logDebug($msg)
{
    echo $msg . "\n";
}

function logErr($msg)
{
    file_put_contents('php://stderr', $msg . "\n");
}

class PagingJsonIterator implements Iterator
{
    protected $curl;
    protected $url;
    protected $totalCount;

    protected $data = [];
    protected $dataPos = 0;
    protected $position = 0;
    protected $hasMore = false;
 
    public function __construct($url)
    {
        $this->url  = $url;
    }
 
    public function rewind()
    {
        $this->position = 0;
        $this->loadData();
    }
 
    public function current()
    {
        return $this->data[$this->position - $this->dataPos];
    }
 
    public function key()
    {
        return $this->position;
    }
 
    public function next()
    {
        ++$this->position;
    }
 
    public function valid()
    {
        if (isset($this->data[$this->position - $this->dataPos])) {
            return true;
        }
        if (!$this->hasMore) {
            //no more data
            return false;
        }
        $this->loadData();
        return isset($this->data[$this->position - $this->dataPos]);
    }
 
    protected function loadData()
    {
        $this->dataPos += count($this->data);
        if ($this->dataPos == 0) {
            $obj = fetchJson($this->url . '&max_id=');
        } else {
            $last = end($this->data);
            $obj = fetchJson($this->url . '&max_id=' . $last->id);
        }

        $this->hasMore = $obj->user->media->page_info->has_next_page;
        $this->data    = $obj->user->media->nodes;
    }
}

$it = new PagingJsonIterator('https://www.instagram.com/' . $profile . '/?__a=1');
foreach ($it as $node) {
    $date     = $node->date;
    $imgUrl   = $node->display_src;
    $videoUrl = null;

    $fileMeta    = $dldir . '/' . $date . '.json';
    $fileDetails = $dldir . '/' . $date . '.details.json';
    $filePub     = $dldir . '/' . $date . '.pub';
    $fileImg     = $dldir . '/' . $date . '.jpg';
    $fileVideo   = $dldir . '/' . $date . '.mp4';
    $fileLoc     = $dldir . '/' . $date . '.location.json';

    if (!file_exists($fileMeta)) {
        file_put_contents($fileMeta, json_encode($node));
        logDebug('New post');
    } else if ($stopAtFirst) {
        //do not go any further
        break;
    }
    if (!file_exists($fileDetails)) {
        $details = fetchJson('https://www.instagram.com/p/' . $node->code . '/?__a=1');
        file_put_contents($fileDetails, json_encode($details));        
        logDebug(' details saved');
    } else {
        $details = json_decode(file_get_contents($fileDetails));
    }
    if (!file_exists($fileImg)) {
        file_put_contents($fileImg, file_get_contents($imgUrl));
        logDebug(' image saved');
    }
    if (isset($details->media->is_video) && $details->media->is_video) {
        if (!file_exists($fileVideo)) {
            file_put_contents(
                $fileVideo, file_get_contents($details->media->video_url)
            );
            logDebug(' video saved');
        }
        $videoUrl = $details->media->video_url;
    }
        
    if (isset($details->media->location->id)) {
        if (!file_exists($fileLoc)) {
            $loc = fetchJson(
                'https://www.instagram.com/explore/locations/'
                . $details->media->location->id . '/?__a=1'
            );
            file_put_contents($fileLoc, json_encode($loc));
            logDebug(' location saved');
        } else {
            $loc = json_decode(file_get_contents($fileLoc));
        }
        $loc = $loc->location;
    }
    if (!file_exists($filePub)) {
        //publish to micropub endpoint
        //var_dump($details);
        //die();

        $title   = '';
        if (isset($node->caption)) {
            $title = $node->caption;
        }
        $postUrl = 'https://www.instagram.com/p/' . $node->code;
        $argVid  = '';
        $argLoc  = '';
        if ($videoUrl !== null) {
            $argVid = ' --file ' . escapeshellarg($videoUrl);
        }
        if (isset($loc)) {
            $html = '<p>Location: '
                . '<a href="'
                . 'http://www.openstreetmap.org/'
                . '?mlat=' . $loc->lat
                . '&amp;mlon=' . $loc->lng
                . '#map=10/' . $loc->lat . '/' . $loc->lng
                . '">' . htmlspecialchars($loc->name) . '</a>'
                . '</p>';
            $argContent = ' --name=' . escapeshellarg($title)
                . ' --html ' . escapeshellarg($html);
            $argLoc = ' -x=' . escapeshellarg(
                'location=geo:' . $loc->lat . ',' . $loc->lng
            );
        } else {
            $argContent = ' ' . escapeshellarg($title);
        }

        $command = $shpub . ' note'
            . ' --published=' . date('c', $date)
            . ' --syndication=' . escapeshellarg($postUrl)
            . ' --file ' . escapeshellarg($imgUrl)
            . $argVid
            . $argLoc
            . $argContent;

        $lastline = exec($command . ' 2>&1', $output, $retval);
        if ($retval != 0) {
            logErr(' ERROR posting to micropub endpoint');
            logErr(implode("\n", $output));
            exit(10);
        }
        file_put_contents($filePub, $lastline);
        logDebug(' published: ' . $lastline);
    }
}
?>
