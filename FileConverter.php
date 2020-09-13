<?php

class FileConverter
{
    private $file;

    function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Start file conversion
     */
    public function convertFile()
    {
        $fileContents = file_get_contents($this->file);
        $fileExtension = strtolower($this->getFileExtension());

        switch ($fileExtension) {
            case 'json':
                $this->jsonToCsv($fileContents);
                $this->jsonToYaml($fileContents);
                break;
            case 'csv':
                $this->csvToJson($fileContents);
                $this->csvToYaml($fileContents);
                break;
            case 'yml':
                $this->yamlToJson($fileContents);
                $this->yamlToCsv($fileContents);
                break;
            default:
        }

        $this->zipConvertedFiles();
        $this->downloadFile();
    }

    /**
     * Converts file from json to csv
     * @param $fileContents
     */
    private function jsonToCsv($fileContents)
    {
        $data = json_decode($fileContents, true);
        $this->createCsvFile($data);

    }

    /**
     * Converts file from json to yaml
     * @param $fileContents
     */
    private function jsonToYaml($fileContents)
    {
        $jsonArray = json_decode($fileContents, true);
        $yamlData = $this->constructYamlData($jsonArray);
        $this->saveFileToTemporaryFolder('converted_file.yml', $yamlData);
    }

    /**
     * Converts file from csv to json
     * @param $fileContents
     */
    private function csvToJson($fileContents)
    {
        $csvArray = $this->constructCsvArray($fileContents);
        $this->saveFileToTemporaryFolder("converted_file.json", json_encode($csvArray));
    }

    /**
     * Converts file from csv to yaml
     * @param $fileContents
     */
    private function csvToYaml($fileContents)
    {
        $csvArray = $this->constructCsvArray($fileContents);
        $yamlData = $this->constructYamlData($csvArray);
        $this->saveFileToTemporaryFolder("converted_file.yml", $yamlData);
    }

    /**
     * Converts file from yaml to json
     * @param $fileContents
     */
    private function yamlToJson($fileContents)
    {
        $parsedYaml = $this->parseYaml($fileContents);
        $this->saveFileToTemporaryFolder("converted_file.json", $parsedYaml);
    }

    /**
     * Converts file yaml csv to csv
     * @param $fileContents
     */
    private function yamlToCsv($fileContents)
    {
        $parsedYaml = $this->parseYaml($fileContents);
        $this->createCsvFile(json_decode($parsedYaml));
    }

    /**
     * Parse into json
     * @param $fileContents
     * @return false|string
     */
    private function parseYaml($fileContents)
    {
        $parsedYaml = yaml_parse($fileContents);
        $yamlArray = [];
        foreach ($parsedYaml as $key => $entry) {
            $this->assignArrayByPath($yamlArray, $key, $entry);
        }

        return json_encode($yamlArray, JSON_PRETTY_PRINT);
    }

    /**
     * Assigns array key by path
     * @param $arr
     * @param $path
     * @param $value
     * @param string $separator
     */
    private function assignArrayByPath(&$arr, $path, $value, $separator = '.')
    {
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            $arr = &$arr[$key];
        }

        $arr = $value;
    }

    /**
     * Constructs csv array
     * @param $fileContents
     * @return array
     */
    private function constructCsvArray($fileContents)
    {
        $data = array_map("str_getcsv", explode("\n", $fileContents));
        $keys = array_shift($data);
        $csvArray = array_map(function ($values) use ($keys) {
            return array_combine($keys, $values);
        }, $data);

        return $csvArray;
    }

    /**
     * Constructs yaml data
     * @param $arrayData
     * @return string
     */
    private function constructYamlData($arrayData)
    {
        $yaml = "";
        $indent = str_repeat(' ', 4);

        foreach ($arrayData as $idx => $yamlRow) {
            $yaml .= "-\n";
            foreach ($yamlRow as $key => $value) {
                $yaml .= "{$indent}{$key}: {$value}\n";
            }
        }

        return $yaml;
    }

    /**
     * Creates csv file
     * @param $data
     */
    private function createCsvFile($data)
    {
        $csvFileName = "converted_files/converted_file.csv";
        $fp = fopen($csvFileName, 'w');
        $header = false;
        foreach ($data as $row) {
            if (empty($header)) {
                $header = array_keys($row);
                fputcsv($fp, $header);
                $header = array_flip($header);
            }
            fputcsv($fp, array_merge($header, $row));
        }
        fclose($fp);
    }

    /**
     * Saves filed to converted_files folder
     * @param $filename
     * @param $fileContent
     */
    private function saveFileToTemporaryFolder($filename, $fileContent)
    {
        $path = "converted_files/$filename";
        $fw = fopen($path, "wb");
        fwrite($fw, $fileContent);
        fclose($fw);
    }

    /**
     * Zips converted files
     */
    private function zipConvertedFiles()
    {
        $zip = new ZipArchive();
        $zip->open('converted_files/converted_files.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $filesToDelete = array();

        foreach (glob("converted_files/*") as $file) {
            $zip->addFile($file);
            $filesToDelete[] = $file;
        }

        $zip->close();

        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }

    /**
     * Trigger zip download in browser
     */
    private function downloadFile()
    {
        $tmp_file = 'converted_files/converted_files.zip';
        ob_start();
        header("Cache-Control: no-cache, must-revalidate");
        header("Content-Type: application/force-download");
        header('Content-disposition: attachment; filename=converted_files.zip');
        header('Content-type: application/zip');
        ob_end_flush();
        readfile($tmp_file);
    }

    /**
     * Verify if URL
     * @return mixed
     */
    private function isUrl()
    {
        return filter_var($this->file, FILTER_VALIDATE_URL);
    }

    /**
     * Determines the file extension
     * @return mixed|string
     */
    private function getFileExtension()
    {
        $fileExtension = "";
        if (empty($this->file)) return $fileExtension;
        if ($this->isUrl()) {
            $fileExtension = pathinfo(parse_url($this->file, PHP_URL_PATH), PATHINFO_EXTENSION);
        } else {
            $fileExtension = pathinfo($this->file, PATHINFO_EXTENSION);
        }

        return $fileExtension;
    }
}
