<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class CustomDynamicObjectsTest extends \PHPUnit_Framework_TestCase
{
    
    protected $customDynamicObjects;
    
    protected $jsonsMock;
    protected $wpConnectorMock;
    protected $capsuleMock;
    
    protected $builderMock;
    protected $blueprintTableMock;
    
    public function setUp() {
        $this->jsonsMock = $this->getMockBuilder('CustomDynamicObjects\Jsons')->setConstructorArgs(['test'])->setMethods(['getObjectTypes'])->getMock();
        
        $this->wpConnectorMock = $this->getMockBuilder('CustomDynamicObjects\WordpressConnector')->setMethods(['add_action', 'add_meta_box'])->getMock();
        
        $this->capsuleMock = $this->getMockBuilder('Capsule')->setMethods(['schema', 'addConnection', 'setAsGlobal'])->getMock();
        
        $this->builderMock = $this->getMockBuilder('Illuminate\Database\Schema\Builder')->disableOriginalConstructor()->setMethods(['create'])->getMock();
        
        $this->blueprintTableMock = $this->getMockBuilder('Illuminate\Database\Schema\Blueprint')->disableOriginalConstructor()->setMethods(['increments', 'string'])->getMock();
        
        $this->customDynamicObjects = new CustomDynamicObjects($this->wpConnectorMock, $this->jsonsMock, $this->capsuleMock);
    }
    
    public function testCreateBackendCallsAddActionWithMetaBoxAndHtmlClassMethod() {
        
        $this->wpConnectorMock->expects($this->once())->method('add_action')->with($this->equalTo('add_meta_boxes') , $this->equalTo([$this->customDynamicObjects, 'addingObjectTypeMetaBox']));
        
        $this->customDynamicObjects->createBackend();
    }
    
    public function testAddingObjectTypeMetaBoxCallsAddMetaBox() {
        
        $this->wpConnectorMock->expects($this->once())->method('add_meta_box')->with($this->equalTo('custom_dynamic_objects') , $this->equalTo('Object Type') , $this->equalTo([$this->customDynamicObjects, 'customDynamicObjectsMetaBox']) , $this->equalTo('post') , $this->equalTo('side') , $this->equalTo('high') , $this->equalTo(null));
        
        $this->customDynamicObjects->addingObjectTypeMetaBox();
    }
    
    public function testCustomDynamicObjectsMetaBoxReturnsEmptyUlOnNoObject() {
        
        $this->jsonsMock->expects($this->once())->method('getObjectTypes')->will($this->returnValue(array()));
        
        $this->customDynamicObjects->customDynamicObjectsMetaBox();
    }
    
    public function testCustomDynamicObjectsMetaBoxWillNotBreakOnNull() {
        
        $this->jsonsMock->expects($this->once())->method('getObjectTypes')->will($this->returnValue(null));
        
        $this->customDynamicObjects->customDynamicObjectsMetaBox();
    }
    
    public function testCustomDynamicObjectsMetaBoxCreatesHtmlListOfObjects() {
        
        $this->jsonsMock->expects($this->once())->method('getObjectTypes')->will($this->returnValue([array(
            'file' => 'media.json'
        ) , array(
            'file' => 'foo.json'
        ) , array(
            'file' => 'test.json'
        ) ]));
        
        $this->expectOutputString('<ul><li>media</li><li>foo</li><li>test</li></ul>');
        
        $this->customDynamicObjects->customDynamicObjectsMetaBox();
    }
    
    public function testMigrateTriggersCapsuleSchemaCreateWithRightTableName() {
        
        $this->jsonsMock->expects($this->once())->method('getObjectTypes')->will($this->returnValue([['file' => 'media.json', 'properties' => []], ['file' => 'test.json', 'properties' => []]]));
        
        $this->builderMock->expects($this->at(0))->method('create')->with($this->equalTo('customDynamicObjects_media'))->will($this->returnValue(null));
        
        $this->builderMock->expects($this->at(1))->method('create')->with($this->equalTo('customDynamicObjects_test'))->will($this->returnValue(null));
        
        $this->capsuleMock->expects($this->exactly(2))->method('schema')->will($this->returnValue($this->builderMock));
        
        $this->customDynamicObjects->migrate();
    }
    
    public function testMigrateTriggersSchemasCreateFunctionWithRightCallbackFunction() {
        
        $this->jsonsMock->expects($this->once())->method('getObjectTypes')->will($this->returnValue([['file' => 'media.json', 'properties' => []], ['file' => 'test.json', 'properties' => [['function' => 'string', 'param' => 'subtitle']]]]));
        
        $this->blueprintTableMock->expects($this->exactly(2))->method('increments')->with($this->equalTo('id'))->will($this->returnValue(null));
        
        $this->blueprintTableMock->expects($this->once())->method('string')->with($this->equalTo('subtitle'))->will($this->returnValue(null));
        
        $this->builderMock->expects($this->atLeastOnce())->method('create')->with($this->anything() , $this->equalTo(function () {
        }))->will($this->returnCallback(function ($name, $callback) {
            echo $callback($this->blueprintTableMock);
        }));
        
        $this->capsuleMock->expects($this->exactly(2))->method('schema')->will($this->returnValue($this->builderMock));
        
        $this->customDynamicObjects->migrate();
    }

    private function setWpdbMock(){
    	$wpdb = new stdClass();
        $wpdb->dbhost = 'testhost';
        $wpdb->dbname = 'testname';
        $wpdb->dbuser = 'testuser';
        $wpdb->dbpassword = 'testpassword';
        $wpdb->prefix = 'testprefix';
    	$GLOBALS['wpdb'] = $wpdb;
    }
    
    public function testAddCOnnectionAddsACapsulePdoConnection() {
        
        $this->capsuleMock->expects($this->once())->method('addConnection')->with($this->equalTo(['driver' => 'mysql', 'host' => 'testhost', 'database' => 'testname', 'username' => 'testuser', 'password' => 'testpassword', 'charset' => 'utf8', 'collation' => 'utf8_general_ci', 'prefix' => 'testprefix']));
        $this->setWpdbMock();
        $this->customDynamicObjects->addConnection();
    }
    
    public function testAddCOnnectionCallsCapsuleSetAsGlobalFunction() {
        
        $this->capsuleMock->expects($this->once())->method('setAsGlobal');
        $this->setWpdbMock();
        $this->customDynamicObjects->addConnection();
    }
}
