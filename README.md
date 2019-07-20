# ScssPHP - Symfony Bundle

This bundle for Symfony Framework, integrates the [scssphp/scssphp](https://github.com/scssphp/scssphp) 
package, which allows you to parse SCSS sources (like Bootstrap) in your application, without need
of Node.js and npm!

The ScssPHP bundle recognizes changes in SCSS source files (or Symfony configuration) and only re-compiles
those changes.  

Also it provides a helpful debugger toolbar entry and a command which allows you 
to compile the SCSS sources from CLI.



## Example Configuration

```yaml
scssphp:
    enabled: true
    autoUpdate: true
    assets:
        "styles/main.css":
            src: "assets/main.scss"
            sourceMap: true
            importPaths:
                - "vendor/twbs/bootstrap/scss"
            variables:
                primary: '#ff0066'

```


## TODO

- Add icon (for profiler)
- Write README
