<?php

class PHPLOC_ParallelAnalyser extends PHPLOC_Analyser {

	private $_cores;

	public function __construct($cores) {
		$this->_cores = $cores;
	}

	public function countFiles(array $files, $countTests) {
        $chunkSize = ceil(count($files) / $this->_cores);
        $chunks = array_chunk($files, $chunkSize);
        $forks = array();
        $tmpDir = sys_get_temp_dir();
        for ($i=0; $i < $this->_cores; $i++) {
                $forks[] = $pid = pcntl_fork();
                if ($pid === 0 ) {
                        parent::countFiles($chunks[$i], $countTests);
                        $filename = "$tmpDir/{$i}_phploc_partAnalysis";
                        $data = serialize($this->count);
                        file_put_contents($filename, $data);
                        die();
         
                }
        }
        do {
            pcntl_wait($status);
            array_pop($forks);
        } while (count($forks) > 0);
        for ($i=0; $i < $this->_cores; $i++) {
            $filename = "$tmpDir/{$i}_phploc_partAnalysis";
            $data = file_get_contents($filename);
            unlink($filename);
         	$this->mergeAndSumCount(unserialize($data));
        }
        $count = $this->getCount($countTests);
         foreach ($files as $file) {
            $directory = dirname($file);

            if (!isset($directories[$directory])) {
                $directories[$directory] = TRUE;
            }
        }
		$count['directories']   = count($directories) - 1;
    	return $count;
	}


	private function mergeAndSumCount($childCount) {
	    foreach ($childCount as $key => $value) {
            $this->count[$key] += $value;
        }
	}

}