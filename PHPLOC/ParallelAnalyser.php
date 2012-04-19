<?php

class PHPLOC_ParallelAnalyser extends PHPLOC_Analyser {

	private $_cores;

    const MESSAGE_LENGTH = 100000;

	public function __construct($cores) {
		$this->_cores = $cores;
	}

	public function countFiles(array $files, $countTests) {
        $chunkSize = ceil(count($files) / $this->_cores);
        $chunks = array_chunk($files, $chunkSize);
        $forks = array();

        $sockets = array();
        for ($i=0; $i < $this->_cores; $i++) {
                if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets) === false) {
                    echo "socket_create_pair failed. Reason: ".socket_strerror(socket_last_error());
                }
                $this->socketPairs[] = $sockets;
                $forks[] = $pid = pcntl_fork();
                if ($pid === 0 ) {
                        parent::countFiles($chunks[$i], $countTests);
                        $data = array(
                            'count' => $this->count,
                            'namespaces' => $this->namespaces,
                            'directories' => $this->directories,
                        );
                        $dataString = serialize($data);
                        socket_set_nonblock($sockets[0]);
                          if(socket_write($sockets[0],str_pad($dataString, self::MESSAGE_LENGTH),
                    self::MESSAGE_LENGTH) === false) {
                            throw new Exception("socket_write() failed. Reason: ".
                                                 socket_strerror(socket_last_error($sockets[0])));
                          }
                        socket_close($sockets[0]);
                        die();
                }
        }
        do {
            pcntl_wait($status);
            array_pop($forks);
        } while (count($forks) > 0);
        
        for ($i=0; $i < $this->_cores; $i++) {
            $sockets = $this->socketPairs[$i];
            $childMessage = '';

            while ($message =  socket_read($sockets[1], self::MESSAGE_LENGTH)) {
                $childMessage .= $message;
            }
            
            $data = unserialize(trim($childMessage));
            socket_close($sockets[1]);
            $this->mergeAndSumCount($data['count']);
            $this->mergeNamespaces($data['namespaces']);
            // $this->mergeDirectories($data['directories']);
        }

        // need to find some way to pass the number of directories from the child to the paent so 
        // we don't have to do this again 
        // same with namespaces which are also a object variable
        $count = $this->getCount($countTests);
         foreach ($files as $file) {
            $directory = dirname($file);

            if (!isset($directories[$directory])) {
                $directories[$directory] = TRUE;
            }
        }
		$count['directories']   = count($this->directories) - 1;
    	return $count;
	}

	private function mergeAndSumCount($childCount) {
	    foreach ($childCount as $key => $value) {
            $this->count[$key] += $value;
        }
	}

    private function mergeNamespaces($namespaces) {
        $this->namespaces = array_merge($namespaces, $this->namespaces);
    }

    private function mergeDirectories($directories) {
        $this->directories = array_merge($directories, $this->directories);
    }
}