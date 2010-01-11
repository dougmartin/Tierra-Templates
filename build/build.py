#!/usr/bin/python

import os, sys, shutil, glob, zipfile, time

release_dir = "releases"

def main(params):
	products = set(['all_products', 'all_files', 'public_private', 'public_only'])
	if len(params) != 2:
		print "usage: %s <version> [%s]" % (sys.argv[0], "|".join(products))
		exit()
	(version, product) = params
	
	if product not in products:
		print "ERROR: unknown product '%s'" % product
		quit()
		
		
	make_dir("releases")
	make_dir("release.tmp")
	
	if (product == "all_files") or (product == "all_products"):
		product_filename = "tierratemplates-all_files-" + version
		clear_temp_dir()
		git_checkout(product_filename)
		update_version_text(product_filename, version)
		create_zip(product_filename)
		
	if (product == "public_private") or (product == "all_products"):
		product_filename = "tierratemplates-public_private-" + version
		clear_temp_dir()
		git_checkout(product_filename)
		update_version_text(product_filename, version)
		build_public_private(product_filename)
		create_zip(product_filename)
	
	if (product == "public_only") or (product == "all_products"):
		product_filename = "tierratemplates-public_only-" + version
		clear_temp_dir()
		git_checkout(product_filename)
		update_version_text(product_filename, version)
		build_all_public(product_filename)
		create_zip(product_filename)
		
	clear_temp_dir()
		
def clear_temp_dir():
	shutil.rmtree("release.tmp", 1)
	
def create_readme_file(base_dir, text):
	fp = open(base_dir + "README.txt", "w")
	fp.write(text)
	fp.close()
		
def remove_extra_files(base_dir):
	shutil.rmtree(base_dir + "build", 1)
	shutil.rmtree(base_dir + "docs", 1)
	shutil.rmtree(base_dir + "test", 1)
	
def move_public_files(base_dir, public_dir):
	for file in ["index.php", ".htaccess"]:
		shutil.move(base_dir + "src/runner/" + file, base_dir + public_dir + file)
	
def move_and_create_private_files(base_dir, private_dir):
	lib_dir = base_dir + private_dir + "lib/tierratemplates/"
	shutil.move(base_dir + "src", lib_dir)
	shutil.rmtree(lib_dir + "kohana", 1)
	shutil.rmtree(lib_dir + "runner", 1)
	shutil.rmtree(lib_dir + "tester", 1)
	make_dir(base_dir + private_dir + "cache")
	make_dir(base_dir + private_dir + "templates")
	create_readme_file(base_dir + private_dir + "cache/", "this directory is used to cache templates")
	create_readme_file(base_dir + private_dir + "templates/", "this directory is where you add your templates")
	
def build_public_private(product_filename):
	base_dir = "release.tmp/" + product_filename + "/"
	make_dir(base_dir + "public")
	make_dir(base_dir + "private")
	move_public_files(base_dir, "public/")
	shutil.move(base_dir + "src/runner/private_config.php", base_dir + "public/config.php")
	move_and_create_private_files(base_dir, "private/")
	remove_extra_files(base_dir)
	
def build_all_public(product_filename):
	base_dir = "release.tmp/" + product_filename + "/"
	move_public_files(base_dir, "")
	shutil.move(base_dir + "src/runner/public_config.php", base_dir + "config.php")
	move_and_create_private_files(base_dir, "")
	remove_extra_files(base_dir)

def git_checkout(product_filename):
	print "checking out git source"
	curdir = os.getcwd()
	os.chdir("..")
	os.system("git checkout-index -a -f --prefix=build/release.tmp/" + product_filename + "/")
	os.chdir(curdir)
	
def update_version_text(product_filename, version):
	print "updating version marker in source"
	curdir = os.getcwd()
	os.chdir("release.tmp/" + product_filename)
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
	
def create_zip(product_filename):
	print "building zip file"
	curdir = os.getcwd()
	zip = zipfile.ZipFile("releases/" + product_filename + ".zip", "w")
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
