<?php
/**
 * Desc:
 * User: baagee
 * Date: 2020/5/22
 * Time: 上午11:10
 */

namespace Sss;
class ProcessBar
{
    protected $total = 0;
    protected $title = '';

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setTotal($total)
    {
        $this->total = $total;
    }

    function update($cur)
    {
        // printf("%s: [%-50s] %d%%\r", $this->title, str_repeat('#', $cur / $this->total * 50), $cur / $this->total * 100);
        // if ($cur >= $this->total) {
        //     echo "\n";
        // }

        printf("%s: [%-50s] %d%%" . PHP_EOL, $this->title, str_repeat('#', $cur / $this->total * 50), $cur / $this->total * 100);
        if ($cur >= $this->total) {
            echo PHP_EOL;
        }
    }
}
