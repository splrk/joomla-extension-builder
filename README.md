# Joomla Extension builder with phing

This project uses phing to read you joomla extension manifest files
and zip them up.  It does some smart rewriting of the manifest file
zipped up so that removed folders and files of your extension are
actually removed on updates

## Using

### composer

You will need to add the github repository as as packace in your
`composer.json` file:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "http://github.com/splrk/joomla-extension-builder"
        }
    ]
}
```

Then require the master branch in your project

```json
{
    "require-dev": {
        "joomla/extension-builder": "master"
    }
}
```

This will install phing, a phing build file and two custom phing tasks.
To get started in your own project, ccreate a build file (build.xml)
with the following code

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project name="your-jooml-extension" default="prepare">
    <import file="vendor/joomla/extension-builder/build.xml" />
</project>
```

Then you can run phing from the command line

```
vendor/bin/phing
```

### How it works

The `prepare` target will search your top directorie's xml files for a valid joomla
XML manifest.  If it finds multiple, it will build them all.  It names the final zip file
based on teh component name and the targeted joomla version. ie. `com_mycomponent_3.5.zip`
or `mod_mymodule_2.5.zip`

If you wish to specify a specific xml, you can use the `createzip` target and set the
`xmlfile` property. eg.

```
vendor/bin/phing createzip -Dxmlfile=manifest.xml
```

The name of the xml does not matter.  The builder will rename it according to the name in the manifest

## Contributing

I'm happy to take a look at pull requests if youi'd like to submit them.  I prefer to use git-flow for naming
branches.
