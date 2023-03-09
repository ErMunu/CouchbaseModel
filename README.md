# Couchbase Model
Couchbase Model will provide generic methods for CRUD operations. This model is designed after finalizing to use subdocuments like tables.

## Create DTO
Create a DTO class with parameter names same as database keys.

### Example of DTO
```php

namespace App\DTO\Content;
class Student
{
    public function __construct(
        public int $roll_no,
        public string $name,
        public string $class,
        public string $section,
        public string $dob,
    )
    {
    }
}

```

## Create Model
Create a model and extend the CouchbaseModel to inherit Couchbase methods in it. then declare basic information as in example below.

### Example of Model
```php
namespace App\Models\DirectoryName;

use App\Models\CouchbaseModel;

class StudentModel extends CouchbaseModel
{
    const COLLECTION_NAME = 'School';
    const DTO_CLASS = '\App\DTO\Content\Student';
    const DOCUMENT_KEY = 'roll_no';
    const COLLECTION_KEY = 'Students';
}
```
