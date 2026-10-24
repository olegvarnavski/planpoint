<?php
require_once dirname(__DIR__).'/model/compat.php';
if (PlanPointPlatform::isV3()) {
    abstract class PlanPointProcessorBase extends \MODX\Revolution\Processors\Processor {}
} else {
    abstract class PlanPointProcessorBase extends modProcessor {}
}
