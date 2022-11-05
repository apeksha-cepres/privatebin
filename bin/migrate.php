<?php

// change this, if your php files and data is outside of your webservers document root
define('PATH', '..' . DIRECTORY_SEPARATOR);

define('PUBLIC_PATH', __DIR__ . DIRECTORY_SEPARATOR);
require PATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use PrivateBin\Configuration;
use PrivateBin\Model;


$longopts = array(
    "delete-after",
    "delete-during"
);
$opts_arr = getopt("fhnv", $longopts, $rest);
if ($opts_arr === false) {
    dieerr("Erroneous command line options. Please use -h");
}
if (array_key_exists("h", $opts_arr)) {
    helpexit();
}

$delete_after    = array_key_exists("delete-after",  $opts_arr);
$delete_during   = array_key_exists("delete-during", $opts_arr);
$force_overwrite = array_key_exists("f", $opts_arr);
$dryrun          = array_key_exists("n", $opts_arr);
$verbose         = array_key_exists("v", $opts_arr);

if ($rest >= $argc) {
    dieerr("Missing source configuration directory");
}
if ($delete_after && $delete_during) {
    dieerr("--delete-after and --delete-during are mutually exclusive");
}

$srcconf = getConfig("source", $argv[$rest]);
$rest++;
$dstconf = getConfig("destination", ($rest < $argc ? $argv[$rest] : ""));

if (($srcconf->getSection("model")         == $dstconf->getSection("model")) &&
    ($srcconf->getSection("model_options") == $dstconf->getSection("model_options"))) {
    dieerr("Source and destination storage configurations are identical");
}

$srcmodel = new Model($srcconf);
$srcstore = $srcmodel->getStore();
$dstmodel = new Model($dstconf);
$dststore = $dstmodel->getStore();
$ids      = $srcstore->getAllPastes();

foreach ($ids as $id) {
    debug("Reading paste id " . $id);
    $paste    = $srcstore->read($id);
    $comments = $srcstore->readComments($id);

    savePaste($force_overwrite, $dryrun, $id, $paste, $dststore);
    foreach ($comments as $comment) {
        saveComment($force_overwrite, $dryrun, $id, $comment, $dststore);
    }
    if ($delete_during) {
        deletePaste($dryrun, $id, $srcstore);
    }
}

if ($delete_after) {
    foreach ($ids as $id) {
        deletePaste($dryrun, $id, $srcstore);
    }
}

debug("Done.");


function deletePaste($dryrun, $pasteid, $srcstore)
{
    if (!$dryrun) {
        debug("Deleting paste id " . $pasteid);
        $srcstore->delete($pasteid);
    } else {
        debug("Would delete paste id " . $pasteid);
    }
}

function saveComment ($force_overwrite, $dryrun, $pasteid, $comment, $dststore)
{
    $parentid  = $comment["parentid"];
    $commentid = $comment["id"];

    if (!$dststore->existsComment($pasteid, $parentid, $commentid)) {
        if (!$dryrun) {
            debug("Saving paste id " . $pasteid . ", parent id " .
                  $parentid . ", comment id " . $commentid);
            $dststore->createComment($pasteid, $parentid, $commentid, $comment);
        } else {
            debug("Would save paste id " . $pasteid . ", parent id " .
                  $parentid . ", comment id " . $commentid);
        }
    } else if ($force_overwrite) {
        if (!$dryrun) {
            debug("Overwriting paste id " . $pasteid . ", parent id " .
                  $parentid . ", comment id " . $commentid);
            $dststore->createComment($pasteid, $parentid, $commentid, $comment);
        } else {
            debug("Would overwrite paste id " . $pasteid . ", parent id " .
                  $parentid . ", comment id " . $commentid);
        }
    } else {
        if (!$dryrun) {
            dieerr("Not overwriting paste id " . $pasteid . ", parent id " .
                   $parentid . ", comment id " . $commentid);
        } else {
            dieerr("Would not overwrite paste id " . $pasteid . ", parent id " .
                   $parentid . ", comment id " . $commentid);
        }
    }
}

function savePaste ($force_overwrite, $dryrun, $pasteid, $paste, $dststore)
{
    if (!$dststore->exists($pasteid)) {
        if (!$dryrun) {
            debug("Saving paste id " . $pasteid);
            $dststore->create($pasteid, $paste);
        } else {
            debug("Would save paste id " . $pasteid);
        }
    } else if ($force_overwrite) {
        if (!$dryrun) {
            debug("Overwriting paste id " . $pasteid);
            $dststore->create($pasteid, $paste);
        } else {
            debug("Would overwrite paste id " . $pasteid);
        }
    } else {
        if (!$dryrun) {
            dieerr("Not overwriting paste id " . $pasteid);
        } else {
            dieerr("Would not overwrite paste id " . $pasteid);
        }
    }
}

function getConfig ($target, $confdir)
{
    debug("Trying to load " . $target . " conf.php" .
          ($confdir === "" ? "" : " from " . $confdir));

    putenv("CONFIG_PATH=" . $confdir);
    $conf = new Configuration;
    putenv("CONFIG_PATH=");

    return $conf;
}

function dieerr ($text)
{
    fprintf(STDERR, "ERROR: %s" . PHP_EOL, $text);
    die(1);
}

function debug ($text) {
    if ($GLOBALS["verbose"]) {
        printf("DEBUG: %s" . PHP_EOL, $text);
    }
}

function helpexit ()
{
    print("migrate.php - Copy data between PrivateBin backends

Usage:
  php migrate.php [--delete-after] [--delete-during] [-f] [-n] [-v] srcconfdir
                  [<dstconfdir>]
  php migrate.php [-h]

Options:
  --delete-after   delete data from source after all pastes and comments have
                   successfully been copied to the destination
  --delete-during  delete data from source after the current paste and its
                   comments have successfully been copied to the destination
  -f               forcefully overwrite data which already exists at the
                   destination
  -n               dry run, do not copy data
  -v               be verbose
  <srcconfdir>     use storage backend configration from conf.php found in
                   this directory as source
  <dstconfdir>     optionally, use storage backend configration from conf.php
                   found in this directory as destination; defaults to:
                   " . PATH . "cfg" . DIRECTORY_SEPARATOR . "conf.php
");
    exit();
}
