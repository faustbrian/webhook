<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Enums;

/**
 * Defines supported primary key types for webhook models.
 *
 * This enum represents the available primary key generation strategies that can
 * be used with the webhook call model. Each type corresponds to a different
 * identifier format with specific characteristics and use cases.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum PrimaryKeyType: string
{
    /**
     * Traditional auto-incrementing integer primary keys.
     *
     * Standard sequential numeric IDs that are automatically incremented
     * by the database. Simplest option but reveals record count and ordering.
     */
    case ID = 'id';

    /**
     * Universally Unique Lexicographically Sortable Identifiers.
     *
     * 26-character case-insensitive strings that are time-ordered and globally
     * unique. Provides better performance than UUIDs while maintaining sortability
     * and avoiding sequential ID enumeration vulnerabilities.
     */
    case ULID = 'ulid';

    /**
     * Universally Unique Identifiers (version 4).
     *
     * 36-character strings (32 hex digits plus 4 hyphens) that are globally unique
     * and cryptographically random. Suitable when global uniqueness is required
     * but chronological ordering is not important.
     */
    case UUID = 'uuid';
}
