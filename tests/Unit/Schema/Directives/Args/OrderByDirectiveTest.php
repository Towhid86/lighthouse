<?php

namespace Tests\Unit\Schema\Directives\Args;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class OrderByDirectiveTest extends DBTestCase
{
    protected $schema = '
    type Query {
        users(orderBy: [OrderByClause!] @orderBy): [User!]! @all
    }

    type User {
        name: String
        team_id: Int
    }    
    ';

    /**
     * @test
     */
    public function itCanOrderByTheGivenFieldAndSortOrderASC()
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->query('
        {
            users(
                orderBy: [
                    {
                        field: "name"
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'A'
                    ],
                    [
                        'name' => 'B'
                    ]
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanOrderByTheGivenFieldAndSortOrderDESC()
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->query('
        {
            users(
                orderBy: [
                    {
                        field: "name"
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'B'
                    ],
                    [
                        'name' => 'A'
                    ]
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanOrderByMultipleFields()
    {
        factory(User::class)->create(['name' => 'B', 'team_id' => 2]);
        factory(User::class)->create(['name' => 'A', 'team_id' => 5]);
        factory(User::class)->create(['name' => 'C', 'team_id' => 2]);

        $this->query('
        {
            users(
                orderBy: [
                    {
                        field: "team_id"
                        order: ASC
                    }
                    {
                        field: "name"
                        order: ASC
                    }
                ]
            ) {
                team_id
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'team_id' => 2,
                        'name' => 'B',
                    ],
                    [
                        'team_id' => 2,
                        'name' => 'C'
                    ],
                    [
                        'team_id' => 5,
                        'name' => 'A'
                    ],
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itThrowsOnInvalidDefinition()
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema('
        type Query {
            foo(bar: Int @orderBy): Int
        }
        ');
    }
}
