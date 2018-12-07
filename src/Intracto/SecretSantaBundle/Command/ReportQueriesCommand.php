<?php

namespace Intracto\SecretSantaBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Intracto\SecretSantaBundle\Service\ExportReportQueriesService;
use Intracto\SecretSantaBundle\Service\ReportQueriesService;

class ReportQueriesCommand extends Command
{
    /**
     * @var ReportQueriesService
     */
    private $reportQueriesService;

    /**
     * @var ExportReportQueriesService
     */
    private $exportReportQueriesService;

    /**
     * @param ReportQueriesService       $reportQueriesService
     * @param ExportReportQueriesService $exportReportQueriesService
     */
    public function __construct(ReportQueriesService $reportQueriesService, ExportReportQueriesService $exportReportQueriesService)
    {
        parent::__construct();
        $this->reportQueriesService = $reportQueriesService;
        $this->exportReportQueriesService = $exportReportQueriesService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('intracto:exportReportQueries')
            ->setDescription('Export report queries')
            ->addArgument(
                'year',
                InputArgument::REQUIRED,
                'Export queries for a given year. Expected a year or the string "all"'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $year = $input->getArgument('year');

        if ('all' !== $year) {
            if (false === strtotime($year)) {
                $year = date('Y');
            }
        }

        $this->exportReportQueriesService->export($this->reportQueriesService->getReportResults($year), $year);
    }
}
