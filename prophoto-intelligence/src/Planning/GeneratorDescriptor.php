<?php

namespace ProPhoto\Intelligence\Planning;

use InvalidArgumentException;

class GeneratorDescriptor
{
    /**
     * @param list<string> $supported_media_kinds
     * @param list<string> $produces_outputs
     * @param list<string> $preferred_session_types
     * @param list<string> $preferred_job_types
     */
    public function __construct(
        public readonly string $generator_type,
        public readonly string $generator_version,
        public readonly array $supported_media_kinds,
        public readonly array $produces_outputs,
        public readonly string $default_model_name,
        public readonly string $default_model_version,
        public readonly bool $requires_session_context = false,
        public readonly array $preferred_session_types = [],
        public readonly array $preferred_job_types = []
    ) {
        if ($this->generator_type === '') {
            throw new InvalidArgumentException('Generator descriptor requires a non-empty generator_type.');
        }
        if ($this->generator_version === '') {
            throw new InvalidArgumentException('Generator descriptor requires a non-empty generator_version.');
        }
        if ($this->default_model_name === '') {
            throw new InvalidArgumentException('Generator descriptor requires a non-empty default_model_name.');
        }
        if ($this->default_model_version === '') {
            throw new InvalidArgumentException('Generator descriptor requires a non-empty default_model_version.');
        }
        if ($this->supported_media_kinds === []) {
            throw new InvalidArgumentException('Generator descriptor requires at least one supported_media_kind.');
        }
        if ($this->produces_outputs === []) {
            throw new InvalidArgumentException('Generator descriptor requires at least one produces_output value.');
        }
    }
}
