<?php
declare(strict_types=1);

class LogParser
{
    private $handle;
    private array $formattedLogData;
    private array $urls = [];

    public function __construct($log_file)
    {
        $this->handle = fopen($log_file, 'r') or exit("Cannot open \"$log_file\" file");

        $this->formattedLogData = [
            'views' => 0,
            'urls' => 0,
            'traffic' => 0,
            'rows' => 0,
            'crawlers' => [
                'Google' => 0,
                'Bing' => 0,
                'Baidu' => 0,
                'Yandex' => 0
            ],
            'statusCodes' => [],
        ];
    }

    public function encodeLogData(): string
    {
        $this->findLogData();
        return json_encode($this->formattedLogData);
    }

    private function findLogData(): void
    {
        $pattern = '/^([^ ]+) ([^ ]+) ([^ ]+) (\[[^\]]+\]) "(.*) (.*) (.*)" ([0-9\-]+) ([0-9\-]+) "(.*)" "(.*)"$/';
        $line = 1;

        while (!feof($this->handle)) {
            $buffer = fgets($this->handle);
            if ($buffer && trim($buffer)) {

                ++$this->formattedLogData['rows']; // number of rows

                if (preg_match($pattern, $buffer, $matches)) {
                    $this->formatLogData($matches);
                } else {
                    error_log("Can't parse line $line: $buffer");
                }
            }
            ++$line;
        }

        $this->closeLogFile();
    }

    private function formatLogData(array $matches): void
    {
        list($wholeMatch, $remote_host, $logName, $user, $time, $method, $url,
            $protocol, $status, $bytes, $referer, $userAgent) = $matches;

        ++$this->formattedLogData['views'];
        $this->formattedLogData['traffic'] += $bytes;
        $this->formattedLogData['urls'] = count($this->urls);

        $google = stripos($userAgent, "googlebot");
        $bing = stripos($userAgent, "bing");
        $baidu = stripos($userAgent, "baidu");
        $yandex = stripos($userAgent, "yandex");

        if ($google) {
            ++$this->formattedLogData['crawlers']['Google'];
        }
        if ($bing) {
            ++$this->formattedLogData['crawlers']['Bing'];
        }
        if ($baidu) {
            ++$this->formattedLogData['crawlers']['Baidu'];
        }
        if ($yandex) {
            ++$this->formattedLogData['crawlers']['Yandex'];
        }

        // count urls
        if (array_key_exists($url, $this->urls)) {
            ++$this->urls[$url];
        } else {
            $this->urls[$url] = 1;
        }

        // count status code
        if (array_key_exists($status, $this->formattedLogData['statusCodes'])) {
            ++$this->formattedLogData['statusCodes'][$status];
        } else {
            $this->formattedLogData['statusCodes'][$status] = 1;
        }
    }

    private function closeLogFile(): void
    {
        fclose($this->handle) or exit('Something went wrong while trying to close the source.');
    }
}

$logParser = new LogParser('access_log');

print_r($logParser->encodeLogData());