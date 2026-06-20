<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryParentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_remove_child_category_from_parent(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $parent = Category::factory()->create();
        $child = Category::factory()->create([
            'parent_id' => $parent->id,
        ]);

        $this->actingAs($director)
            ->patch(route('admin.categories.remove-parent', $child))
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('categories', [
            'id' => $child->id,
            'parent_id' => null,
        ]);
    }
}
