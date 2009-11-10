<?php


# XXX: Does this function do anything different than PHP's built-in 'glob' function?
# Returns an array containing filenames for all files matching $pattern within
# the directory $dir.
function getFilesInDir($dir, $pattern = '*', $getHiddenFiles = true) {
  @ $handle = opendir($dir);
  if (!$handle) {
    throw new Exception("Could not read directory $dir");
  }

  $files = array();
  while (false !== ($f = readdir($handle))) {
    if ($f != '.' && $f != '..' && ($getHiddenFiles || $f[0] != '.') && fnmatch($pattern, $f)) {
      $files[] = $f;
    }
  }

  closedir($handle);
  //sort($files);

  return $files;
}


# Returns an array containing a relative path to each file within the directory $dir.
# Each path will be relative to the directory $dir.  Directories are traversed recursively.
# No directory names will be returned, only files (and links, etc).
function recursivelyGetFilesInDir($dir, $pattern = '*', $getHiddenFiles = true) {
  $dir = tidyDirName($dir);

  $files_to_return = array();
  $files_in_dir = getFilesInDir($dir, $pattern, $getHiddenFiles);

  foreach ($files_in_dir as $this_file) {

    $full_path_to_file = "$dir/$this_file";

    if (is_dir($full_path_to_file)) {
      $files_in_subdir = recursivelyGetFilesInDir($full_path_to_file);
      $parent_dir = $this_file;
      foreach ($files_in_subdir as $f) {
        $files_to_return[] = "$this_file/$f";
      }
    } else {
      $files_to_return[] = $this_file;
    }
  }

  return $files_to_return;
}





    // ***********************************************************************
    //
    // get_subdirs( $dir, $pattern, $hidden_dirs )
    //
    // returns an array containing directory names for all sub-directories
    // matching $pattern within the directory $dir.
    //
    // ***********************************************************************

    function get_subdirs( $dir, $pattern = '*', $hidden_dirs = false )
    {
        // eliminate any trailing / character
        if( substr($dir, -1) == '/' ) {
            $dir = substr_replace($dir, '', -1);
        }


        @ $handle = opendir($dir);
        if (!$handle) {
            return null;
        }


        $i = 0;
        $subdir = array();

        // loop through directory...
        while (false !== ($f = readdir($handle))) {

            if( $f != '.'  &&  $f != '..'  &&
              ($hidden_dirs || $f[0] != '.')  &&
              fnmatch($pattern, $f)  &&  is_dir("$dir/$f") ) {
                $subdir[$i++] = $f;
            }
        }

        closedir($handle);
        sort($subdir);

        return $subdir;
    }





    // ***********************************************************************
    //
    // num_files_in_dir( $dir )
    //
    // returns the number of files that exist in the directory specified.
    //
    // ***********************************************************************

    function num_files_in_dir( $dir, $pattern = '*', $getHiddenFiles = false )
    {
        @ $handle = opendir($dir);
        if (!$handle) {
            return null;
        }

        $num_files = 0;

        // loop through directory...
        while (false !== ($f = readdir($handle))) {
            if($f != '.'  &&  $f != '..'  &&
              ($getHiddenFiles || $f[0] != '.')  &&
              fnmatch($pattern, $f))
                ++$num_files;
        }

        return $num_files;
    }





    // ***********************************************************************
    //
    // is_dir_empty( $dir )
    //
    // returns true if the given directory has any files in it.
    //
    // ***********************************************************************

    function is_dir_empty( $dir )
    {
        @ $handle = opendir($dir);
        if( !$handle ) {
            return true;
        }

        // loop through directory...
        while( false !== ($f = readdir($handle)) ) {
            if( $f != '.' && $f != '..' )
                return false;
        }

        return true;
    }





    // ***********************************************************************
    //
    // delete_dir( $file )
    //
    // attempts to delete the directory/file specified as $file.
    //
    // ***********************************************************************

    function delete_dir( $file )
    {
        $success = false;

        if( is_link($file) or is_file($file) ) {
            $success = unlink($file);
        }
        else if( is_dir($file) ) {

            $handle = opendir($file);
            while( $filename = readdir($handle) ) {
                if( $filename != "." and $filename != ".." ) {
                    if( !delete_dir(pathJoin($file, $filename)) ) {
                        $success = false;
                    }
                }
            }
            closedir($handle);
            $success = rmdir($file);
        }
        else if( file_exists($file) ) {

            // if we aren't dealing with a symbolic link, a regular file, or a
            // directory, then we won't try to guess how we should handle it...
            throw new Exception("unknown filesystem node type for file "
              . $file);
        }

        return $success;
    }





    // ***********************************************************************
    //
    // make_dir( $dir )
    //
    // TODO: make this function cross-platform compatible...  right now, it will almost certainly
    //       only work on Unix-based OS's - if it will even work on all of those...
    //       it'd probably be best to rewrite it as a recursive function that
    //       just uses PHP's mkdir() function.
    //
    // make a directory, and its parent directories, if they do not exist.
    //
    // ***********************************************************************

    function make_dir( $dir )
    {
        if( !$dir or strlen($dir) == 0 ) {
            trigger_error("no directory was specified", E_USER_WARNING);
            return;
        }

        $output = $return_value = null;
        exec('mkdir -p ' . escapeshellarg($dir), $output, $return_value);
        return !$return_value;
    }





    // ***********************************************************************
    //
    // move_dir( $source, $dest )
    //
    // move a directory from $source to $dest, including all subdirectories...
    //
    // XXX: this function should not copy the source file if it can't move
    //      the directory/file !!!  but better check that non of the function's
    //      clients depend on this behaviour!!!
    //
    // ***********************************************************************

    function move_dir( $source, $dest )
    {
        if( is_dir($source) ) {

            // if it's a directory, call move_dir() recursively, on all
            // contents of the directory...

            $source = tidyDirName($source);

            if( !is_writable($source) || !is_readable($source) )
                return false;

            $handle = opendir($source);
            make_dir($dest);

            while( $filename = readdir($handle) ) {

                if( $filename != "." && $filename != ".." ) {

                    if( !move_dir($source."/".$filename, $dest."/".$filename) )
                        return false;
                }
            }

            closedir($handle);

            if( ! @rmdir($source) )
                return false;
        }
        else {

            if( ! @rename($source, $dest) ) {
                return false;
            }

            // NOTE: some versions of PHP don't seem to update the "stat cache"
            // after renaming a file (i'm using 5.0.5-2ubuntu1.2 right now).
            // so, we'll manually clear the cache, so the file_exists()
            // operation, below, will return the correct answer -- 2006-03-16.
            clearstatcache();

            // NOTE: there appears to be some kind of bug in rename()
            // (for some PHP versions, at least).
            // sometime it will give a warning "Operation not permitted",
            // however it still returns true!
            // SO, we'll make sure the file was actually moved...
            if( file_exists($source) ) {

                if( ! @copy($source, $dest) ) {
                    return false;
                }

                if( ! @unlink($dest) ) {
                    return false;
                }
            }
        }

        return true;
    }





    // ***********************************************************************
    //
    // tidy up a directory name.
    // examples:
    //  /path/to/dir       => /path/to/dir
    //  xtra///slashes//   => xtra/slashes
    //  /dot/./slash/.     => /dot/slash
    //  path/../to         => path/../to
    //  ./some/path        => some/path
    //
    // (note that no special case is made for "..")
    //
    // ***********************************************************************

    function tidyDirName( $dir_name )
    {
        $result = '';

        $components = explode('/', $dir_name);

        // the first component is a special case...
        if( $components[0] == '.' ) {

            // if the $dir_name started with a "./", then we'll remove it, and
            // begin with our component at index 1, which will be whatever
            // directly followed "./".
            $result .= @ $components[1];
            unset($components[1]);
        }
        else if( $components[0] != '' ) {

            // for this case, the $dir_name is a relative path without a "./"
            // in front of it.
            $result .= $components[0];
        }
        unset($components[0]);

        foreach( $components as $thisOne )
        {
            if( $thisOne != '' && $thisOne != '.' ) {
                $result .= '/' . $thisOne;
            }
        }

        return $result;
    }





    // ***********************************************************************
    //
    // determines whether or not the first parameter, $item, is a
    // sub-directory of, the same directory as, or a file within the given
    // $parentdir.  this function will not actually check for the existence of
    // either directory, but only compare string values.
    //
    // ***********************************************************************

    function is_within_or_is_directory( $item, $parentdir )
    {
        $parentdir = tidyDirName($parentdir);
        return( substr($item, 0, strlen($parentdir)) == $parentdir );
    }





    // ***********************************************************************
    //
    // determines whether or not the first parameter, $item, is a
    // sub-directory of or a file inside of the given $parentdir.
    // unlike is_within_or_is_directory(), above, this function will return
    // false if $item appears to be the same directory as $parentdir.
    // this function will not actually check for the existence of either
    // directory, but only compare string values.
    //
    // ***********************************************************************

    function is_within_directory( $item, $parentdir )
    {
        $parentdir = tidyDirName($parentdir);
        return( substr($item, 0, strlen($parentdir)) == $parentdir &&
                strlen($item) > (strlen($parentdir) + 1));
    }





    // ***********************************************************************
    //
    // get_highest_level_existing_dir( $path )
    //
    // this function will determine the highest level directory -- which is a
    // prefix of $path -- that exists.
    //
    // for instance, if $path is /dir1/dir2/dir3/ and /dir1/ exists, but not
    // the sub-directories dir2/dir3/, then this function will return /dir1/.
    //
    // ***********************************************************************

    function get_highest_level_existing_dir( $path )
    {
        // start with the directory/file passed
        $current_dir = $path;


        do {

            // if the directory exists, return true.
            if( file_exists($current_dir) ) {
                return $current_dir;
            }

            // save current directory and retrieve its parent directory.
            $last_dir = $current_dir;
            $current_dir = dirname($current_dir);


          // loop until we have no more parent directories.
        } while( $last_dir != $current_dir );

        return null;
    }


# Are $dir1 and $dir2 the same directory?
# This function will resolve all symbolic links and et al. to determine whether the two
# locations specified actually reference the same physical directory.  Also, if the system is
# a Windows system, we will do a case-insensitive comparison.
function areSameDir($dir1, $dir2) {
  $dir1 = realpath($dir1);
  $dir2 = realpath($dir2);
  if (strtoupper(substr(php_uname(), 0, 3)) == "WIN") { # If Windows, compare case-insensitively
    return !strcasecmp($dir1, $dir2);
  } else {
    return !strcmp($dir1, $dir2);
  }
}
