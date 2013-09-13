<?php
/**
 * Copyright 2010-2012 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Aws\CloudFront\Iterator;

use Aws\Common\Iterator\AbstractResourceIterator;
use Guzzle\Service\Resource\Model;

/**
 * Iterate over a CloudFront command
 */
class DefaultIterator extends AbstractResourceIterator
{
    /**
     * {@inheritdoc}
     */
    protected function applyNextToken()
    {
        $this->command->set('Marker', $this->nextToken);
    }

    /**
     * {@inheritdoc}
     */
    protected function determineNextToken(Model $result)
    {
        if (isset($result['IsTruncated']) && $result['IsTruncated'] == 'true') {
            $this->nextToken = isset($result['NextMarker']) ? $result['NextMarker'] : false;
        } else {
            $this->nextToken = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function handleResults(Model $result)
    {
        return isset($result['Items']) ? $result['Items'] : array();
    }
}
