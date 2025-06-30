# Morden Image Optimizer Database Schema

## Custom Tables

### wp_mio_optimization_log
Stores detailed logs of all optimization operations.

**Columns:**
- `log_id` (bigint, primary key): Unique identifier for each log entry
- `attachment_id` (bigint, indexed): WordPress attachment ID
- `optimization_status` (varchar): Status of optimization (success, failed, skipped)
- `optimization_method` (varchar): Method used (imagick, gd, tinypng, resmushit)
- `original_size` (int): Original file size in bytes
- `optimized_size` (int): Optimized file size in bytes
- `savings_bytes` (int): Bytes saved during optimization
- `timestamp` (datetime): When the optimization occurred

### wp_mio_optimization_queue
Manages the queue for asynchronous optimization processing.

**Columns:**
- `queue_id` (bigint, primary key): Unique identifier for each queue item
- `attachment_id` (bigint, unique): WordPress attachment ID
- `status` (varchar, indexed): Queue status (pending, processing, failed)
- `added_at` (datetime): When item was added to queue
- `retries` (tinyint): Number of retry attempts

## WordPress Meta Usage

### wp_postmeta
The plugin uses the following meta keys:

- `_mio_optimized` (boolean): Whether image has been optimized
- `_mio_backup_path` (string): Path to backup file
- `_mio_optimization_method` (string): Method used for optimization
- `_mio_original_size` (int): Original file size
- `_mio_optimized_size` (int): Optimized file size
- `_mio_savings` (int): Bytes saved

### wp_options
Plugin settings stored in:

- `mio_settings`: Main plugin configuration
- `mio_db_version`: Database schema version
