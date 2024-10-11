<?php

namespace App\Traits;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait ModelAction
{
    /**
     * Bulk action update/delete
     *
     * @param Request $request
     * @param array $modelData
     * @return array
     */
    private function bulkAction(Request $request, string $dependent_column = null, array $modelData) {

        $status  = 'success';
        $message = translate("Successfully performed bulk action");
        $model   = $modelData['model'];
        $ids     = $request->input('ids', []);
        
        if (empty($ids)) {

            return ['error', translate("No items selected")];
        }
        
        $type = $request->input('type');
        
        try {
            DB::beginTransaction();

            if ($type === 'delete') {

                foreach ($ids as $id) {

                    $item = $model::find($id);
                    if ($item) {

                        $this->deleteWithRelations($item);
                    }
                }
                $message = translate("Successfully deleted selected items");
            } elseif ($type === 'status') {
                
                $statusValue = $request->input('status');
                
                $model::whereIn('id', $ids)->update([
                    'status' => $statusValue
                ]);
                if($dependent_column && $statusValue == StatusEnum::FALSE->status()) {

                    $model::whereIn('id', $ids)->update([
                        $dependent_column => $statusValue
                    ]);
                }
                $message = translate("Successfully updated status for selected items");
            }
            DB::commit();
        } catch (\Exception $exception) {

            DB::rollBack();
            return ['error', translate("Server Error: ") . $exception->getMessage()];
        }

        return [$status, $message];
    }

    /**
     * Delete model with its relations
     *
     * @param Model $model
     * @return void
     */
    private function deleteWithRelations($model) {

        if (method_exists($model, 'getRelationships')) {

            foreach ($model->getRelationships() as $relation) {

                $relatedItems = $model->$relation()->get();
                foreach ($relatedItems as $relatedItem) {
                    
                    $relatedItem->delete();
                }
            }
        }
        $model->delete();
    }
}
