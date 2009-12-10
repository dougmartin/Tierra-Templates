#!/usr/bin/python

import os, sys, shutil, glob, zipfile, time

release_dir = "releases"

def main(params):
	if len(params) != 2:
		print "usage: %s (all|empty_runner|sample_runner) <version>" % sys.argv[0]
		exit()
	(product, version) = params
			
	make_dir("releases")
	
	make_dir("release.tmp")
	shutil.rmtree("release.tmp", 1)
		
	if product == "all":
		build_all(version)
	elif product == "empty_runner":
		build_empty_runner(version)
	elif product == "sample_runner":
		build_sample_runner(version)
	else:
		print "ERROR: unknown product"

	shutil.rmtree("release.tmp", 1)
		
def build_all(version):
	git_checkout("all", version)
	update_version_text("all", version)
	create_zip("all", version)
	
def build_empty_runner(version):
	print "build empty runner not implemented"
	pass
	
def build_sample_runner(version):
	print "build samplew runner not implemented"
	pass

def get_filename(product, version):
	return "tierratemplates-" + product + "-" + version
	
def git_checkout(product, version):
	print "checking out git source"
	curdir = os.getcwd()
	os.chdir("..")
	os.system("git checkout-index -a -f --prefix=build/release.tmp/" + get_filename(product, version) + "/")
	os.chdir(curdir)
	
def update_version_text(product, version):
	print "updating version marker in source"
	curdir = os.getcwd()
	os.chdir("release.tmp/" + get_filename(product, version))
	for root, dirs, files in os.walk(''):
		for file in files:
			if len(root) > 0:
				path = root + "\\" + file
			else:
				path = file
			f = open(path, "r")
			contents = f.read()
			f.close()
			f = open(path, "w")
			f.write(contents.replace("- %VERSION%", "- Version " + version))
			f.close()
	os.chdir(curdir)
	
def create_zip(product, version):
	print "building zip file"
	curdir = os.getcwd()
	zip = zipfile.ZipFile("releases/tierratemplates-" + get_filename(product, version) + ".zip", "w")
	os.chdir("release.tmp")
	for root, dirs, files in os.walk(''):
		for file in files:
			if len(root) > 0:
				path = root + "\\" + file
			else:
				path = file
			zip.write(path)
	zip.close()
	os.chdir(curdir)

def make_dir(dir):
	try:
		os.makedirs(dir)
	except WindowsError:
		pass

	
if __name__ == "__main__":
	main(sys.argv[1:])
