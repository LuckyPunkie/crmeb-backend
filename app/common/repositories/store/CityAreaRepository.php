<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\common\repositories\store;


use app\common\dao\store\CityAreaDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * 省市区
 */
class CityAreaRepository extends BaseRepository
{
    public function __construct(CityAreaDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取指定父ID的所有子项
     *
     * 本函数通过查询具有特定父ID的数据项来获取所有子项。
     * 使用了ORM模式的search方法来构建查询条件，然后通过select方法执行查询并返回结果。
     *
     * @param int $pid 父ID，用于指定要查询的子项的父项。
     * @return array 返回查询结果，是一个包含所有指定父ID子项的数组。
     */
    public function getChildren($pid)
    {
        // 根据$pid查询满足条件的数据，并返回所有查询结果
        return $this->search(['pid' => $pid])->select();
    }


    /**
     * 根据条件获取列表数据
     *
     * 本函数旨在通过提供的条件从数据库中检索列表数据。它使用了DAO模式来执行查询，
     * 并对查询结果进行了额外的处理，以包括父级数据和子级数据的相关信息。
     *
     * @param string|array $where 查询条件，可以是字符串或数组形式的SQL WHERE子句条件。
     * @return \Illuminate\Database\Eloquent\Collection 返回一个包含搜索结果的集合，这些结果已经包含了父级数据和子级数据的附加信息。
     */
    public function getList($where)
    {
        // 执行搜索查询，根据$where条件，带上父级数据，按ID升序排序
        return  $this->dao->getSearch($where)
            ->with(['parent']) // 加载每个条目的父级数据
            ->order('id ASC') // 按ID升序排序结果
            ->select() // 执行查询并返回结果集
            ->append(['children','hasChildren']); // 附加'children'和'hasChildren'属性到每个条目，用于后续处理或显示
    }

    /**
     * 创建或编辑城市区域表单
     *
     * 本函数用于生成城市或区域的添加或编辑表单。根据传入的$id$和$parentId$，决定是进行编辑操作还是添加操作。
     * 如果$id$存在，则从数据库中获取相应数据进行编辑；如果$id$不存在但$parentId$存在，则以$parentId$为基准进行添加操作。
     * 表单中包含的字段有：上级区域ID、级别、上级区域名称、区域名称。
     *
     * @param int|null $id 区域ID，如果存在则用于编辑区域，否则用于添加区域。
     * @param int|null $parentId 上级区域ID，用于指定新区域的上级区域。
     * @return mixed 返回表单对象，包含表单的规则、标题和数据。
     * @throws ValidateException 如果根据$id$查询不到数据，则抛出验证异常。
     */
    public function form(?int $id, ?int $parentId)
    {
        // 定义根区域，即全国，作为默认的上级区域。
        $parent = ['id' => 0, 'name' => '全国', 'level' => 0,];
        $formData = [];

        // 如果$id$存在，进行编辑操作。
        if ($id) {
            // 根据$id$查询数据库，获取区域数据。
            $formData = $this->dao->getWhere(['id' => $id],'*',['parent'])->toArray();
            // 如果查询不到数据，则抛出异常。
            if (!$formData) throw new ValidateException('数据不存在');

            // 创建编辑表单，并设置表单的提交URL。
            $form = Elm::createForm(Route::buildUrl('systemCityAreaUpdate', ['id' => $id])->build());

            // 如果存在上级区域数据，则更新$parent变量。
            if (!is_null($formData['parent'])) $parent = $formData['parent'];
        } else {
            // 如果$id$不存在，进行添加操作。
            // 创建添加表单，并设置表单的提交URL。
            $form = Elm::createForm(Route::buildUrl('systemCityAreaCreate')->build());

            // 如果$parentId$存在，查询对应的上级区域数据。
            if ($parentId) $parent = $this->dao->getWhere(['id' => $parentId]);
        }

        // 设置表单的验证规则和默认值。
        $form->setRule([
            Elm::input('parent_id', '', $parent['id'] ?? 0)->hiddenStatus(true),
            Elm::input('level', '', $parent['level'] + 1)->hiddenStatus(true),
            Elm::input('parent_name', '上级地址：', $parent['name'])->disabled(true)->placeholder('请输入上级地址'),
            Elm::input('name', '地址名称：', '')->placeholder('请输入地址名称')->required('请输入地址名称'),
        ]);

        // 设置表单的标题，并返回表单对象。
        return $form->setTitle($id ? '编辑城市' : '添加城市')->formData($formData);
    }

    /**
     * 创建新记录
     *
     * 本函数用于在数据库中创建新的记录。在创建记录之前，它会检查传入的数据中是否包含父记录ID（parent_id）。
     * 如果父记录ID大于0，说明该记录有一个父记录，函数会先更新父记录的snum字段（可能表示子记录数）。
     * 最后，函数会调用DAO对象的create方法来实际创建记录。
     *
     * @param array $data 包含新记录数据的数组。数组应包含所有必要的字段，可能包括父记录ID。
     * @return mixed 返回DAO创建操作的结果。具体类型取决于DAO的实现。
     */
    public function create($data)
    {
        // 检查是否有父记录并更新其snum字段
        if($data['parent_id'] > 0){
            // 修改父级snum
            $this->dao->incField($data['parent_id'],'snum');
        }
        // 调用DAO的create方法来创建新记录
        return $this->dao->create($data);
    }


    /**
     * 添加
     * @param $name
     * @param $pid
     * @param $lv
     * @return mixed
     * @author Qinii
     * @day 2023/8/2
     */
    public function treeCreate($name,$code, $pid = 0, $lv = 1)
    {
        $type = [
            1 => 'province',
            2 => 'city',
            3 => 'area',
            4 => 'street',
        ];
        $path = '/';
        if ($pid){
            $res =  $this->dao->get($pid);
            $path = $res['path'].$res['id'].'/';
        }
        $data = [
            'type' => $type[$lv],
            'parent_id' => $pid,
            'level' => $lv,
            'name' => $name,
            'path'=> $path,
            'code' => $code
        ];
        $result =  $this->dao->findOrCreate($data);
        return $result->id;
    }

    /**
     * 计算子集个数
     * @author Qinii
     * @day 2023/8/2
     */
    public function sumChildren($pid = '')
    {
        $data = $this->dao->getSearch(['parent_id' => $pid])->where('level','<',4)->select();
        foreach ($data as $datum) {
            $snum = $this->dao->getSearch(['parent_id' => $datum->id])->count();
            $datum->snum = $snum;
            $datum->save();
        }
    }

    /**
     *  文件倒入，地址信息
     * @param $fiel
     * @author Qinii
     * @day 2024/1/19
     */
    public function updateCityForTxt($fiel)
    {
        $fiel = json_decode(file_get_contents($fiel));
        $this->tree($fiel);
        return true;
    }

    /**
     *  循环整理地址信息
     * @param $data
     * @param $pid
     * @param $path
     * @param $level
     * @return bool
     * @author Qinii
     * @day 2024/1/19
     */
    public function tree($data,$pid = 0,$path = '/',$level = 1)
    {
        $type = [
            1 => 'province',
            2 => 'city',
            3 => 'area',
            4 => 'street'
        ];
        foreach ($data as $k => $datum) {
            $_path = '';
            $where = [
                'code' => $datum->code,
                'name' => $datum->name,
                'path' => $path,
                'level'=> $level,
                'parent_id' => $pid,
                'type' => $type[$level]
            ];
            $rest = $this->dao->findOrCreate($where);
            if (isset($datum->children)) {
                $_path = $path.$rest->id.'/';
                $this->tree($datum->children, $rest->id, $_path, $level +1);
            }
        }
        return true;
    }


    /**
     * 删除特定ID的数据项。
     *
     * 此方法首先尝试根据给定的ID获取数据项。如果数据项不存在，则抛出一个验证异常，
     * 表明请求删除的数据不存在。如果数据项存在且有父项（表示它不是最顶级项），
     * 则递减父项的计数器（可能是表示子项数量的字段），最后删除该数据项。
     *
     * @param int $id 要删除的数据项的唯一标识符。
     * @return bool 返回删除操作的结果，通常是TRUE表示删除成功。
     * @throws ValidateException 如果尝试删除的数据项不存在，则抛出此异常。
     */
    public function delete($id)
    {
        // 尝试根据ID获取数据项。
        $res = $this->dao->get($id);

        // 如果获取的结果为空，即数据项不存在，则抛出异常。
        if (empty($res)) {
            throw new ValidateException('数据不存在');
        }

        // 如果数据项有父项（表示它不是最顶级项），则递减其父项的计数器。
        if ($res['parent_id'] > 0) {
            // 修改父级snum
            $this->dao->decField($res['parent_id'], 'snum');
        }

        // 执行实际的删除操作并返回结果。
        return $this->dao->delete($id);
    }

}
